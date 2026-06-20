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

def main():
    if len(sys.argv) != 9:
        print(json.dumps({
            "success": False,
            "error": "Usage: python3 olt_optical_info.py <host> <username> <password> <frame> <slot> <port> <ont_id> <ssh_port>"
        }))
        sys.exit(1)

    host = sys.argv[1]
    username = sys.argv[2]
    password = sys.argv[3]
    frame = sys.argv[4]
    slot = sys.argv[5]
    port = sys.argv[6]
    ont_id = sys.argv[7]
    ssh_port = int(sys.argv[8])

    device = {
        "device_type": "huawei_olt",
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