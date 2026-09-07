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


def build_commands(action, profile_id, profile_name="", dba_type="type4", max_bandwidth=0):
    action = str(action or "").lower().strip()
    dba_type = str(dba_type or "type4").lower().strip()

    if action == "add":
        if not profile_name:
            raise ValueError("Profile name is required for add.")
        if max_bandwidth <= 0:
            raise ValueError("Max bandwidth is required for add.")

        return [
            f'dba-profile add profile-id {profile_id} profile-name "{profile_name}" {dba_type} max {max_bandwidth}'
        ]

    if action == "delete":
        return [
            f"dba-profile delete profile-id {profile_id}"
        ]

    raise ValueError("Unsupported action. Use add or delete.")


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

        action = str(data.get("action", "")).lower().strip()
        profile_id = int(data.get("profile_id", 0))
        profile_name = str(data.get("profile_name", "")).strip()
        dba_type = str(data.get("dba_type", data.get("profile_type", "type4"))).strip()
        max_bandwidth = int(data.get("max_bandwidth", data.get("max", 0)) or 0)

        dry_run = bool(data.get("dry_run", False))
        save_config = bool(data.get("save_config", False))
        global_delay_factor = float(data.get("global_delay_factor", 2))
        read_timeout = int(data.get("read_timeout", 30))

        if not host:
            fail("Missing OLT host.")
        if not username:
            fail("Missing OLT username.")
        if not password:
            fail("Missing OLT password.")
        if profile_id <= 0:
            fail("Invalid DBA profile ID.")
        if action not in ("add", "delete"):
            fail("Invalid action. Use add or delete.")

        commands = build_commands(
            action=action,
            profile_id=profile_id,
            profile_name=profile_name,
            dba_type=dba_type,
            max_bandwidth=max_bandwidth
        )

        if dry_run:
            ok(
                message="Dry-run successful. Commands generated only.",
                commands=commands,
                output="",
                meta={
                    "host": host,
                    "port": port,
                    "action": action,
                    "profile_id": profile_id,
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

        if (
            "% unknown command" in lowered
            or "error:" in lowered
            or "failure:" in lowered
            or "invalid" in lowered
        ):
            fail(
                "DBA profile command failed on OLT.",
                extra={
                    "commands": commands,
                    "output": final_output
                }
            )

        ok(
            message=f"DBA profile {action} completed successfully.",
            commands=commands,
            output=final_output,
            meta={
                "host": host,
                "port": port,
                "action": action,
                "profile_id": profile_id,
                "dry_run": False,
                "save_config": save_config,
                "prompt_before": prompt_before,
                "prompt_after_config": prompt_after_config
            }
        )

    except Exception as exc:
        fail(str(exc), extra={"traceback": traceback.format_exc()})

    finally:
        if conn is not None:
            try:
                conn.disconnect()
            except Exception:
                pass


if __name__ == "__main__":
    main()
