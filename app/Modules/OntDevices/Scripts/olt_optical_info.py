import json
import re
import sys
from netmiko import ConnectHandler

def parse_optical_detail(output):
    def extract(pattern, cast=str):
        m = re.search(pattern, output, re.MULTILINE)
        if not m:
            return None
        value = m.group(1).strip()
        try:
            return cast(value)
        except Exception:
            return value

    return {
        "onu_nni_port_id": extract(r"ONU NNI port ID\s*:\s*(.+)"),
        "module_type": extract(r"Module type\s*:\s*(.+)"),
        "module_sub_type": extract(r"Module sub-type\s*:\s*(.+)"),
        "used_type": extract(r"Used type\s*:\s*(.+)"),
        "encapsulation_type": extract(r"Encapsulation Type\s*:\s*(.+)"),
        "optical_power_precision_dbm": extract(r"Optical power precision\(dBm\)\s*:\s*([-\d\.]+)", float),
        "vendor_name": extract(r"Vendor name\s*:\s*(.+)"),
        "vendor_rev": extract(r"Vendor rev\s*:\s*(.+)"),
        "vendor_pn": extract(r"Vendor PN\s*:\s*(.+)"),
        "vendor_sn": extract(r"Vendor SN\s*:\s*(.+)"),
        "date_code": extract(r"Date Code\s*:\s*(.+)"),
        "rx_power_dbm": extract(r"Rx optical power\(dBm\)\s*:\s*([-\d\.]+)", float),
        "tx_power_dbm": extract(r"Tx optical power\(dBm\)\s*:\s*([-\d\.]+)", float),
        "laser_bias_current_ma": extract(r"Laser bias current\(mA\)\s*:\s*([-\d\.]+)", float),
        "temperature_c": extract(r"Temperature\(C\)\s*:\s*([-\d\.]+)", float),
        "voltage_v": extract(r"Voltage\(V\)\s*:\s*([-\d\.]+)", float),
        "olt_rx_ont_power_dbm": extract(r"OLT Rx ONT optical power\(dBm\)\s*:\s*([-\d\.]+)", float),
        "catv_rx_power_dbm": extract(r"CATV Rx optical power\(dBm\)\s*:\s*([-\d\.\-]+)")
    }

def parse_distance_from_all(output, ont_id):
    lines = output.splitlines()

    for line in lines:
        m = re.match(
            r"^\s*(\d+)\s+([-\d\.]+|-)\s+([-\d\.]+|-)\s+([-\d\.]+|-)\s+([-\d\.]+|-)\s+([-\d\.]+|-)\s+([-\d\.]+|-)\s+([-\d\.]+|-)\s*$",
            line
        )
        if not m:
            continue

        row_ont_id = int(m.group(1))
        if row_ont_id != int(ont_id):
            continue

        raw_distance = m.group(8)
        try:
            return int(float(raw_distance))
        except Exception:
            return raw_distance

    return None

def parse_location_by_serial(output):
    patterns = [
        r"F/S/P\s*:\s*(\d+)\s*/\s*(\d+)\s*/\s*(\d+).*?ONT-ID\s*:\s*(\d+)",
        r"ONT-ID\s*:\s*(\d+).*?F/S/P\s*:\s*(\d+)\s*/\s*(\d+)\s*/\s*(\d+)",
    ]
    for index, pattern in enumerate(patterns):
        match = re.search(pattern, output, re.IGNORECASE | re.DOTALL)
        if not match:
            continue
        values = [int(value) for value in match.groups()]
        if index == 0:
            frame, slot, port, ont_id = values
        else:
            ont_id, frame, slot, port = values
        return frame, slot, port, ont_id

    # Some Huawei releases print a compact table instead of labelled fields.
    match = re.search(
        r"^\s*(\d+)\s*/\s*(\d+)\s*/\s*(\d+)\s+(\d+)\s+\S+\s+\S+",
        output,
        re.MULTILINE,
    )
    if match:
        return tuple(int(value) for value in match.groups())
    return None

def main():
    raw = sys.stdin.read()
    if not raw.strip():
        print(json.dumps({
            "success": False,
            "error": "Missing JSON payload."
        }))
        sys.exit(1)

    try:
        data = json.loads(raw)
    except Exception:
        print(json.dumps({"success": False, "error": "Invalid JSON payload."}))
        sys.exit(1)

    host = str(data.get("host", "")).strip()
    username = str(data.get("username", "")).strip()
    password = str(data.get("password", "")).strip()
    serial = str(data.get("serial", "")).strip().upper()
    frame = data.get("frame")
    slot = data.get("slot")
    port = data.get("port")
    ont_id = data.get("ont_id")
    ssh_port = int(data.get("ssh_port", 22))

    if not host or not username or not password:
        print(json.dumps({"success": False, "error": "OLT connection details are incomplete."}))
        sys.exit(1)

    device = {
        "device_type": "huawei",
        "host": host,
        "username": username,
        "password": password,
        "port": ssh_port,
        "fast_cli": False,
        "global_delay_factor": 2,
    }

    try:
        conn = ConnectHandler(**device)
        conn.enable()

        # Enter config mode first
        config_output = conn.send_command_timing(
            "config",
            strip_prompt=False,
            strip_command=False
        )

        if any(value is None for value in (frame, slot, port, ont_id)):
            if not serial or not re.fullmatch(r"[A-Z0-9._:-]{4,64}", serial):
                raise RuntimeError("A valid ONT serial number is required for automatic optical lookup.")
            lookup_output = conn.send_command_timing(
                f"display ont info by-sn {serial}",
                strip_prompt=False,
                strip_command=False,
            )
            location = parse_location_by_serial(lookup_output)
            if location is None:
                raise RuntimeError(f"ONT serial {serial} was not found on this OLT.")
            frame, slot, port, ont_id = location

        frame, slot, port, ont_id = (int(frame), int(slot), int(port), int(ont_id))

        # Enter GPON interface mode
        intf_output = conn.send_command_timing(
            f"interface gpon {frame}/{slot}",
            strip_prompt=False,
            strip_command=False
        )

        # Run detailed optical command
        detail_output = conn.send_command_timing(
            f"display ont optical-info {port} {ont_id}",
            strip_prompt=False,
            strip_command=False
        )

        # Run summary/all command for distance
        all_output = conn.send_command_timing(
            f"display ont optical-info {port} all",
            strip_prompt=False,
            strip_command=False
        )

        conn.disconnect()

        parsed = parse_optical_detail(detail_output)
        parsed["distance_m"] = parse_distance_from_all(all_output, ont_id)

        print(json.dumps({
            "success": True,
            "data": {
                "host": host,
                "frame": int(frame),
                "slot": int(slot),
                "port": int(port),
                "ont_id": int(ont_id),
                **parsed
            }
        }))
    except Exception as e:
        print(json.dumps({
            "success": False,
            "error": str(e)
        }))

if __name__ == "__main__":
    main()
