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
    }, ensure_ascii=False))
    sys.exit(code)


def ok(message, commands=None, output="", meta=None):
    print(json.dumps({
        "success": True,
        "message": message,
        "commands": commands or [],
        "output": output,
        "meta": meta or {}
    }, ensure_ascii=False))
    sys.exit(0)


def parse_payload():
    raw = sys.stdin.read()
    if not raw.strip():
        fail("Missing JSON payload.")

    try:
        return json.loads(raw)
    except Exception:
        fail("Invalid JSON payload.")


def build_add_commands(data):
    profile_id = int(data.get("profile_id", 0))
    profile_name = str(data.get("profile_name", "")).strip()
    customer_cvlan = int(data.get("customer_cvlan", 0))
    management_vlan = int(data.get("management_vlan", 25))
    dba_profile_id = int(data.get("dba_profile_id", 20))

    tcont_id = int(data.get("tcont_id", 1))
    gem_subscriber_id = int(data.get("gem_subscriber_id", 1))
    gem_management_id = int(data.get("gem_management_id", 2))
    tr069_ip_index = int(data.get("tr069_ip_index", 1))

    if profile_id <= 0:
        fail("Invalid line profile ID.")
    if not profile_name:
        fail("Missing line profile name.")
    if customer_cvlan <= 0 or customer_cvlan > 4094:
        fail("Invalid customer CVLAN.")
    if management_vlan <= 0 or management_vlan > 4094:
        fail("Invalid management VLAN.")
    if dba_profile_id <= 0:
        fail("Invalid DBA profile ID.")

    return [
        f'ont-lineprofile gpon profile-id {profile_id} profile-name "{profile_name}"',
        "omcc encrypt on",
        "tr069-management enable",
        f"tr069-management ip-index {tr069_ip_index}",
        f"tcont {tcont_id} dba-profile-id {dba_profile_id}",
        f"gem add {gem_subscriber_id} eth tcont {tcont_id} encrypt on",
        f"gem add {gem_management_id} eth tcont {tcont_id} encrypt on",
        f"gem mapping {gem_subscriber_id} 0 vlan {customer_cvlan}",
        f"gem mapping {gem_management_id} 0 vlan {management_vlan}",
        "commit",
        "quit",
    ]


def build_delete_commands(data):
    profile_id = int(data.get("profile_id", 0))

    if profile_id <= 0:
        fail("Invalid line profile ID.")

    return [
        f"undo ont-lineprofile gpon profile-id {profile_id}"
    ]


def join_outputs(parts):
    return "\n\n".join([p for p in parts if p])


def main():
    conn = None

    try:
        data = parse_payload()

        host = str(data.get("host", "")).strip()
        username = str(data.get("username", "")).strip()
        password = str(data.get("password", "")).strip()
        port = int(data.get("port", 22))
        action = str(data.get("action", "")).strip().lower()

        dry_run = bool(data.get("dry_run", False))
        save_config = bool(data.get("save_config", False))
        global_delay_factor = float(data.get("global_delay_factor", 2))
        read_timeout = int(data.get("read_timeout", 45))

        if not host:
            fail("Missing OLT host.")
        if not username:
            fail("Missing OLT username.")
        if not password:
            fail("Missing OLT password.")
        if action not in ("add", "delete"):
            fail("Invalid action. Use add or delete.")

        commands = build_add_commands(data) if action == "add" else build_delete_commands(data)

        if dry_run:
            ok(
                message="Dry-run successful. Commands generated only.",
                commands=commands,
                meta={
                    "host": host,
                    "port": port,
                    "action": action,
                    "profile_id": int(data.get("profile_id", 0)),
                    "dry_run": True
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

            if "Are you sure" in result or "[Y/N]" in result or "(y/n)" in result.lower():
                confirm = conn.send_command_timing(
                    "y",
                    strip_prompt=False,
                    strip_command=False,
                    read_timeout=read_timeout
                )
                result = f"{result}\n{confirm}"

            outputs.append(f"$ {cmd}\n{result}")

        if save_config:
            save_result = conn.send_command_timing(
                "save",
                strip_prompt=False,
                strip_command=False,
                read_timeout=read_timeout
            )

            if "Are you sure" in save_result or "[Y/N]" in save_result or "(y/n)" in save_result.lower():
                confirm_result = conn.send_command_timing(
                    "y",
                    strip_prompt=False,
                    strip_command=False,
                    read_timeout=read_timeout
                )
                save_result = f"{save_result}\n{confirm_result}"

            outputs.append(f"$ save\n{save_result}")

        final_output = join_outputs(outputs)
        lowered = final_output.lower()

        if "% unknown command" in lowered or "failure:" in lowered or "error:" in lowered:
            fail(
                f"Line profile {action} command failed on OLT.",
                extra={
                    "commands": commands,
                    "output": final_output
                }
            )

        ok(
            message=f"Line profile {action} completed successfully.",
            commands=commands,
            output=final_output,
            meta={
                "host": host,
                "port": port,
                "action": action,
                "profile_id": int(data.get("profile_id", 0)),
                "dry_run": False,
                "save_config": save_config,
                "prompt_before": prompt_before,
                "prompt_after_config": prompt_after_config
            }
        )

    except Exception as exc:
        fail(str(exc), {"traceback": traceback.format_exc()})

    finally:
        if conn is not None:
            try:
                conn.disconnect()
            except Exception:
                pass


if __name__ == "__main__":
    main()
