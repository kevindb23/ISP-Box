#!/usr/bin/env python3
import json
import sys
import traceback
from netmiko import ConnectHandler


def fail(message, extra=None, code=1):
    print(json.dumps({
        "success": False,
        "message": message,
        "extra": extra or {}
    }))
    sys.exit(code)


def ok(message, commands=None, output="", meta=None):
    print(json.dumps({
        "success": True,
        "message": message,
        "commands": commands or [],
        "output": output,
        "meta": meta or {}
    }))
    sys.exit(0)


def normalize_vlan_type(vlan_type: str) -> str:
    vlan_type = str(vlan_type or "").strip().upper().replace("-", "_")

    if vlan_type in ("MGMT", "MGMT_VLAN"):
        return "MGMT_VLAN"
    if vlan_type in ("C_VLAN", "CVLAN"):
        return "C_VLAN"
    if vlan_type in ("S_VLAN", "SVLAN"):
        return "S_VLAN"

    return vlan_type


def build_commands(vlan_id: int, vlan_type: str, frame=None, slot=None, port_no=None):
    vlan_type = normalize_vlan_type(vlan_type)

    if vlan_type == "MGMT_VLAN":
        if frame is None or slot is None or port_no is None:
            raise ValueError("MGMT_VLAN requires an OLT port.")
        return [f"vlan {vlan_id} smart", f"port vlan {vlan_id} {int(frame)}/{int(slot)} {int(port_no)}"]

    if vlan_type == "C_VLAN":
        return [f"vlan {vlan_id} smart"]

    if vlan_type == "S_VLAN":
        return [
            f"vlan {vlan_id} smart",
            f"vlan attrib {vlan_id} q-in-q",
            f"vlan forwarding {vlan_id} vlan-connect"
        ]

    raise ValueError(f"Unsupported vlan_type: {vlan_type}")


def parse_payload():
    raw = sys.stdin.read()
    if not raw.strip():
        fail("Missing JSON payload.")

    try:
        return json.loads(raw)
    except Exception:
        fail("Invalid JSON payload.")


def has_cli_error(output: str) -> bool:
    lowered = output.lower()
    return any(x in lowered for x in [
        "error:",
        "failed",
        "unknown command",
        "invalid parameter",
        "% "
    ])


def main():
    conn = None

    try:
        data = parse_payload()

        host = data.get("host")
        username = data.get("username")
        password = data.get("password")
        vlan_id = int(data.get("vlan_id", 0))
        vlan_type = normalize_vlan_type(data.get("vlan_type"))

        if not host or not username or not password:
            fail("Missing device credentials.")

        if vlan_id < 1 or vlan_id > 4094:
            fail("Invalid VLAN ID.")

        commands = build_commands(vlan_id, vlan_type, data.get("frame"), data.get("slot"), data.get("port_no"))

        device = {
            "device_type": "huawei_olt",
            "host": host,
            "username": username,
            "password": password,
            "fast_cli": False,
            "global_delay_factor": 2
        }

        conn = ConnectHandler(**device)
        conn.enable()

        outputs = []

        # Enter config
        outputs.append(conn.send_command_timing("config"))

        # Execute VLAN commands
        for cmd in commands:
            outputs.append(conn.send_command_timing(cmd))

        # ✅ ALWAYS SAVE CONFIG
        save_result = conn.send_command_timing("save")

        if "y/n" in save_result.lower() or "[y/n]" in save_result.lower():
            save_result += conn.send_command_timing("y")

        outputs.append(save_result)

        final_output = "\n".join(outputs)

        if has_cli_error(final_output):
            fail("OLT command failed", {"output": final_output})

        ok(
            message=f"{vlan_type} deployed and saved successfully.",
            commands=commands,
            output=final_output
        )

    except Exception as e:
        fail(str(e), {"traceback": traceback.format_exc()})

    finally:
        if conn:
            try:
                conn.disconnect()
            except:
                pass


if __name__ == "__main__":
    main()
