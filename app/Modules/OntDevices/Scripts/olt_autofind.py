import json
import re
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
    device = {
        "device_type": "huawei_olt",
        "host": "10.0.10.157",
        "username": "ispadmin",
        "password": "N3t3ng777$$$$",
        "fast_cli": False,
        "global_delay_factor": 2
    }

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