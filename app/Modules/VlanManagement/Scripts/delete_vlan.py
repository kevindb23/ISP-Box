#!/usr/bin/env python3
import json
import sys
import traceback
from netmiko import ConnectHandler


def fail(message, extra=None, code=1):
    payload = {
        "success": False,
        "message": message,
        "extra": extra or {}
    }
    print(json.dumps(payload, ensure_ascii=False))
    sys.exit(code)


def ok(message, commands=None, output="", meta=None):
    payload = {
        "success": True,
        "message": message,
        "commands": commands or [],
        "output": output,
        "meta": meta or {}
    }
    print(json.dumps(payload, ensure_ascii=False))
    sys.exit(0)


def normalize_vlan_type(vlan_type: str) -> str:
    vlan_type = str(vlan_type or "").strip().upper().replace("-", "_")

    if vlan_type in ("MGMT", "MGMT_VLAN", "MANAGEMENT", "MANAGEMENT_VLAN"):
        return "MGMT_VLAN"

    if vlan_type in ("C_VLAN", "CVLAN"):
        return "C_VLAN"

    if vlan_type in ("S_VLAN", "SVLAN"):
        return "S_VLAN"

    return vlan_type


def parse_payload():
    if len(sys.argv) < 2:
        fail("Missing JSON payload argument.")

    raw = sys.argv[1]

    try:
        return json.loads(raw)
    except Exception:
        fail("Invalid JSON payload.", {"raw": raw})


def join_outputs(parts):
    return "\n\n".join([p for p in parts if p])


def build_delete_commands(vlan_id: int, vlan_type: str):
    vlan_type = normalize_vlan_type(vlan_type)

    if vlan_type == "S_VLAN":
        return [
            f"vlan forwarding {vlan_id} vlan-mac",
            f"undo vlan attrib {vlan_id}",
            f"undo vlan {vlan_id}"
        ]

    if vlan_type in ("C_VLAN", "MGMT_VLAN"):
        return [
            f"undo vlan {vlan_id}"
        ]

    raise ValueError(f"Unsupported vlan_type: {vlan_type}")


def has_cli_error(output: str) -> bool:
    lowered = output.lower()

    error_markers = [
        "% unknown command",
        "unknown command",
        "error:",
        "failure:",
        "invalid parameter",
        "incomplete command",
        "too many parameters",
        "wrong parameter",
        "parameter error",
        "command is being used by another user"
    ]

    return any(marker in lowered for marker in error_markers)


def vlan_removed_successfully(verify_output: str) -> bool:
    lowered = verify_output.lower()

    success_markers = [
        "vlan does not exist",
        "the vlan does not exist",
        "vlan not exist",
        "failure: the vlan does not exist"
    ]

    return any(marker in lowered for marker in success_markers)


def save_configuration(conn, read_timeout: int):
    save_result = conn.send_command_timing(
        "save",
        strip_prompt=False,
        strip_command=False,
        read_timeout=read_timeout
    )

    lowered = save_result.lower()

    if "are you sure" in lowered or "[y/n]" in lowered or "(y/n)" in lowered or "y/n" in lowered:
        confirm_result = conn.send_command_timing(
            "y",
            strip_prompt=False,
            strip_command=False,
            read_timeout=read_timeout
        )
        save_result = f"{save_result}\n{confirm_result}"

    return save_result


def main():
    conn = None

    try:
        data = parse_payload()

        host = str(data.get("host", "")).strip()
        username = str(data.get("username", "")).strip()
        password = str(data.get("password", "")).strip()
        port = int(data.get("port", 22))
        vlan_id = int(data.get("vlan_id", 0))
        vlan_type = normalize_vlan_type(data.get("vlan_type", "C_VLAN"))

        dry_run = bool(data.get("dry_run", False))
        save_config = bool(data.get("save_config", True))
        global_delay_factor = float(data.get("global_delay_factor", 2))
        read_timeout = int(data.get("read_timeout", 30))

        if not host:
            fail("Missing OLT host.")
        if not username:
            fail("Missing OLT username.")
        if not password:
            fail("Missing OLT password.")
        if vlan_id <= 0 or vlan_id > 4094:
            fail("Invalid VLAN ID. Must be 1-4094.")
        if vlan_type not in ("C_VLAN", "S_VLAN", "MGMT_VLAN"):
            fail("Invalid VLAN type.", {
                "received_vlan_type": vlan_type,
                "allowed_vlan_types": ["C_VLAN", "S_VLAN", "MGMT_VLAN"]
            })

        commands = build_delete_commands(vlan_id, vlan_type)

        if dry_run:
            ok(
                message="Dry-run successful. Delete commands generated only.",
                commands=commands,
                output="",
                meta={
                    "host": host,
                    "port": port,
                    "vlan_id": vlan_id,
                    "vlan_type": vlan_type,
                    "dry_run": True,
                    "save_config": save_config
                }
            )

        device = {
            "device_type": "huawei_olt",
            "host": host,
            "username": username,
            "password": password,
            "port": port,
            "fast_cli": False,
            "global_delay_factor": global_delay_factor,
        }

        conn = ConnectHandler(**device)
        conn.enable()

        outputs = []

        prompt_before = conn.find_prompt()
        outputs.append(f"$ prompt_before\n{prompt_before}")

        config_result = conn.send_command_timing(
            "config",
            strip_prompt=False,
            strip_command=False,
            read_timeout=read_timeout
        )
        outputs.append(f"$ config\n{config_result}")

        prompt_after_config = conn.find_prompt()
        outputs.append(f"$ prompt_after_config\n{prompt_after_config}")

        for cmd in commands:
            result = conn.send_command_timing(
                cmd,
                strip_prompt=False,
                strip_command=False,
                read_timeout=read_timeout
            )
            outputs.append(f"$ {cmd}\n{result}")

        verify_output = conn.send_command_timing(
            f"display vlan {vlan_id}",
            strip_prompt=False,
            strip_command=False,
            read_timeout=read_timeout
        )
        outputs.append(f"$ display vlan {vlan_id}\n{verify_output}")

        if save_config:
            save_result = save_configuration(conn, read_timeout)
            outputs.append(f"$ save\n{save_result}")

        final_output = join_outputs(outputs)

        if has_cli_error(final_output) and not vlan_removed_successfully(verify_output):
            fail(
                "VLAN delete command failed on OLT.",
                extra={
                    "commands": commands,
                    "output": final_output
                }
            )

        if vlan_removed_successfully(verify_output):
            ok(
                message=f"{vlan_type} removed and saved successfully.",
                commands=commands,
                output=final_output,
                meta={
                    "host": host,
                    "port": port,
                    "vlan_id": vlan_id,
                    "vlan_type": vlan_type,
                    "dry_run": False,
                    "save_config": save_config,
                    "prompt_before": prompt_before,
                    "prompt_after_config": prompt_after_config
                }
            )

        fail(
            "Delete command ran, but VLAN still appears to exist on OLT.",
            extra={
                "commands": commands,
                "output": final_output
            }
        )

    except Exception as exc:
        fail(
            str(exc),
            extra={
                "traceback": traceback.format_exc()
            }
        )
    finally:
        if conn is not None:
            try:
                conn.disconnect()
            except Exception:
                pass


if __name__ == "__main__":
    main()