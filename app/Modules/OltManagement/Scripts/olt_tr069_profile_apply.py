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
    if len(sys.argv) < 2:
        fail("Missing JSON payload argument.")

    try:
        return json.loads(sys.argv[1])
    except Exception:
        fail("Invalid JSON payload.", {"raw": sys.argv[1]})


def join_outputs(parts):
    return "\n\n".join([p for p in parts if p])


def build_commands(data):
    action = str(data.get("action", "")).strip().lower()
    profile_id = int(data.get("profile_id", 0))

    if profile_id <= 0:
        raise ValueError("Invalid profile_id.")

    if action == "add":
        profile_name = str(data.get("profile_name", "")).strip()
        acs_url = str(data.get("acs_url", "")).strip()
        acs_username = str(data.get("acs_username", "")).strip()
        acs_password = str(data.get("acs_password", "")).strip()

        if not profile_name:
            raise ValueError("Missing profile_name.")
        if not acs_url:
            raise ValueError("Missing acs_url.")

        return [
            (
                f'ont tr069-server-profile add profile-id {profile_id} '
                f'profile-name "{profile_name}" url "{acs_url}" '
                f'user "{acs_username}" "{acs_password}"'
            )
        ]

    if action == "delete":
        return [
            f"ont tr069-server-profile delete profile-id {profile_id}"
        ]

    raise ValueError("Invalid action. Use add or delete.")


def main():
    conn = None

    try:
        data = parse_payload()

        host = str(data.get("host", "")).strip()
        username = str(data.get("username", "")).strip()
        password = str(data.get("password", "")).strip()
        port = int(data.get("port", 22))

        action = str(data.get("action", "")).strip().lower()
        profile_id = int(data.get("profile_id", 0))

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

        commands = build_commands(data)

        if dry_run:
            ok(
                message="Dry-run successful. Commands generated only.",
                commands=commands,
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

            if action == "delete":
                lowered = result.lower()
                if "are you sure" in lowered or "[y/n]" in lowered or "(y/n)" in lowered:
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

            lowered_save = save_result.lower()
            if "are you sure" in lowered_save or "[y/n]" in lowered_save or "(y/n)" in lowered_save:
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

        failure_markers = [
            "% unknown command",
            "% incomplete command",
            "error locates",
            "failure:",
            "failed"
        ]

        if any(marker in lowered for marker in failure_markers):
            fail(
                f"TR069 profile {action} command failed on OLT.",
                extra={
                    "commands": commands,
                    "output": final_output
                }
            )

        ok(
            message=f"TR069 profile {action} completed successfully.",
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