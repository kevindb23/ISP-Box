import json
import re
import sys
from netmiko import ConnectHandler

def parse_ont(output):
    onts = []

    sections = re.split(r'\n\s*Number\s+:\s+\d+\s*\n', output)

    for block in sections:

        if "Ont SN" not in block:
            continue

        sn = re.search(r'Ont SN\s+: ([A-Z0-9]+)', block)
        fsp = re.search(r'F/S/P\s+: ([0-9/]+)', block)
        vendor = re.search(r'VendorID\s+: ([A-Z0-9]+)', block)
        model = re.search(r'Ont EquipmentID\s+: ([A-Z0-9\-]+)', block)
        time = re.search(r'Ont autofind time\s+: ([0-9\-\:\+ ]+)', block)

        vendor_code = vendor.group(1) if vendor else None

        vendor_map = {
            "HWTC": "Huawei",
            "NBEL": "Nokia",
            "ZTEG": "ZTE"
        }

        onts.append({
            "serial_number": sn.group(1) if sn else None,
            "model": model.group(1) if model else None,
            "vendor": vendor_map.get(vendor_code, vendor_code),
            "vendor_code": vendor_code,
            "fsp": fsp.group(1) if fsp else None,
            "status": "NEW",
            "last_seen": time.group(1).split('+')[0] if time else None
        })

    return onts


def main():
    raw = sys.stdin.read()
    if not raw.strip():
        print(json.dumps({"success": False, "error": "Missing JSON payload."}))
        return

    try:
        data = json.loads(raw)
    except Exception:
        print(json.dumps({"success": False, "error": "Invalid JSON payload."}))
        return

    device = {
        "device_type": "huawei",
        "host": str(data.get("host", "")).strip(),
        "username": str(data.get("username", "")).strip(),
        "password": str(data.get("password", "")).strip(),
        "port": int(data.get("ssh_port", 22)),
        "fast_cli": False,
        "global_delay_factor": 2
    }

    if not device["host"] or not device["username"] or not device["password"]:
        print(json.dumps({"success": False, "error": "OLT connection details are incomplete."}))
        return

    try:
        conn = ConnectHandler(**device)

        conn.enable()

        # 🔥 IMPORTANT: DO NOT use screen-length
        output = conn.send_command_timing(
            "display ont autofind all",
            strip_prompt=False,
            strip_command=False
        )

        conn.disconnect()

        parsed = parse_ont(output)

        print(json.dumps({
            "success": True,
            "data": parsed
        }))

    except Exception as e:
        print(json.dumps({
            "success": False,
            "error": str(e)
        }))


if __name__ == "__main__":
    main()
