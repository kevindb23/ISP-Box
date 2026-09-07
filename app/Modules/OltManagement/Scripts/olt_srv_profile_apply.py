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


def join_outputs(parts):
    return "\n\n".join([p for p in parts if p])


def build_add_commands(profile_id, profile_name, eth_port_count):
    commands = [
        f'ont-srvprofile gpon profile-id {profile_id} profile-name "{profile_name}"',
        f'ont-port eth {eth_port_count}',
    ]

    for i in range(1, eth_port_count + 1):
        commands.append(f"port vlan eth {i} transparent")

    commands.extend([
        "commit",
        "quit",
    ])

    return commands


def build_delete_commands(profile_id):
    return [
        f"undo ont-srvprofile gpon profile-id {profile_id}"
    ]


def output_has_failure(output):
    lowered = output.lower()

    if "% unknown command" in lowered:
        return True
    if "% incomplete command" in lowered:
        return True
    if "error:" in lowered:
        return True
    if "failure:" in lowered:
        return True

    # Huawei may print: success: 1, failed: 0
    # That is OK. Only failed count > 0 is treated as failure.
    for n in range(1, 10):
        if f"failed: {n}" in lowered:
            return True

    return False


def main():
    conn = None

    try:
        data = parse_payload()

        host = str(data.get("host", "")).strip()
        username = str(data.get("username", "")).strip()
        password = str(data.get("password", "")).strip()
        port = int(data.get("port", 22))

        action = str(data.get("action", "add")).strip().lower()
        profile_id = int(data.get("profile_id", 0))
        profile_name = str(data.get("profile_name", "")).strip()
        eth_port_count = int(data.get("eth_port_count", data.get("eth_ports", 4)))

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
        if action not in ("add", "delete"):
            fail("Invalid action. Use add or delete.")
        if profile_id <= 0 or profile_id > 8192:
            fail("Invalid SRV profile ID. Must be 1-8192.")

        if action == "add":
            if not profile_name:
                fail("Profile name is required for add action.")
            if eth_port_count <= 0 or eth_port_count > 4:
                fail("ETH port count must be 1-4.")
            commands = build_add_commands(profile_id, profile_name, eth_port_count)
        else:
            commands = build_delete_commands(profile_id)

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
            "device_type": "huawei",
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

        if output_has_failure(final_output):
            fail(
                f"SRV profile {action} command failed on OLT.",
                extra={
                    "commands": commands,
                    "output": final_output
                }
            )

        ok(
            message=f"SRV profile {action} completed successfully.",
            commands=commands,
            output=final_output,
            meta={
                "host": host,
                "port": port,
                "action": action,
                "profile_id": profile_id,
                "eth_port_count": eth_port_count,
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
