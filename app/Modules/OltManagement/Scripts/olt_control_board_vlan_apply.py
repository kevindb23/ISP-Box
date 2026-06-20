#!/usr/bin/env python3
import json
import sys
import time
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

    raw = sys.argv[1]

    try:
        return json.loads(raw)
    except Exception:
        fail("Invalid JSON payload.", {"raw": raw})


def build_commands(action, vlan_id, frame, slot, port_no):
    action = str(action or "add").lower().strip()
    board_path = f"{frame}/{slot}"

    if action == "add":
        return [f"port vlan {vlan_id} {board_path} {port_no}"]

    if action == "delete":
        return [f"undo port vlan {vlan_id} {board_path} {port_no}"]

    raise ValueError("Unsupported action. Use add or delete.")


def join_outputs(parts):
    return "\n\n".join([p for p in parts if p])


def send_timing(conn, command, read_timeout=30):
    result = conn.send_command_timing(
        command,
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

    time.sleep(0.5)
    return result


def main():
    conn = None

    try:
        data = parse_payload()

        host = str(data.get("host", "")).strip()
        username = str(data.get("username", "")).strip()
        password = str(data.get("password", "")).strip()
        ssh_port = int(data.get("port", 22))

        action = str(data.get("action", "add")).lower().strip()
        vlan_id = int(data.get("vlan_id", 0))
        vlan_type = str(data.get("vlan_type", "")).upper().strip()

        frame = int(data.get("frame", 0))
        slot = int(data.get("slot", -1))

        # Keep compatibility, but prefer PHP payload key: port_no
        port_no = int(data.get(
            "port_no",
            data.get("control_port", data.get("control_board_port", -1))
        ))

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

        if action not in ("add", "delete"):
            fail("Invalid action. Use add or delete.")

        if vlan_id <= 0 or vlan_id > 4094:
            fail("Invalid VLAN ID. Must be 1-4094.")

        if vlan_type and vlan_type not in ("SERVICE", "MGMT"):
            fail("Invalid VLAN type. Use SERVICE or MGMT.")

        if frame < 0:
            fail("Invalid frame.")

        if slot not in (3, 4):
            fail("Invalid control board slot. Expected slot 3 or 4.")

        if port_no < 0 or port_no > 3:
            fail("Invalid control board port. Expected port 0 to 3.")

        commands = build_commands(
            action=action,
            vlan_id=vlan_id,
            frame=frame,
            slot=slot,
            port_no=port_no
        )

        if dry_run:
            ok(
                message="Dry-run successful. Commands generated only.",
                commands=commands,
                output="",
                meta={
                    "host": host,
                    "ssh_port": ssh_port,
                    "action": action,
                    "vlan_id": vlan_id,
                    "vlan_type": vlan_type,
                    "frame": frame,
                    "slot": slot,
                    "control_board": f"{frame}/{slot}",
                    "port_no": port_no,
                    "dry_run": True,
                    "save_config": save_config
                }
            )

        device = {
            "device_type": "huawei_olt",
            "host": host,
            "username": username,
            "password": password,
            "port": ssh_port,
            "fast_cli": False,
            "global_delay_factor": global_delay_factor,
        }

        conn = ConnectHandler(**device)
        conn.enable()

        outputs = []

        prompt_before = conn.find_prompt()
        outputs.append(f"$ prompt_before\n{prompt_before}")
        outputs.append(f"$ target\n{host}:{ssh_port}")
        outputs.append(f"$ action\n{action}")
        outputs.append(f"$ vlan\n{vlan_id}")
        outputs.append(f"$ control_board_port\n{frame}/{slot} {port_no}")

        config_result = send_timing(conn, "config", read_timeout)
        outputs.append(f"$ config\n{config_result}")

        prompt_after_config = conn.find_prompt()
        outputs.append(f"$ prompt_after_config\n{prompt_after_config}")

        for cmd in commands:
            result = send_timing(conn, cmd, read_timeout)
            outputs.append(f"$ {cmd}\n{result}")

        if save_config:
            save_result = send_timing(conn, "save", read_timeout)
            outputs.append(f"$ save\n{save_result}")

        final_output = join_outputs(outputs)
        lowered = final_output.lower()

        fatal_errors = [
            "% unknown command",
            "unknown command",
            "failure:",
            "invalid command",
            "command error",
            "syntax error",
        ]

        for err in fatal_errors:
            if err in lowered:
                fail(
                    "Control board VLAN command failed on OLT.",
                    extra={
                        "commands": commands,
                        "output": final_output
                    }
                )

        if action == "add":
            if "error:" in lowered or "failed" in lowered:
                fail(
                    "Failed to bind VLAN to control board port on OLT.",
                    extra={
                        "commands": commands,
                        "output": final_output
                    }
                )

        if action == "delete":
            idempotent_success_phrases = [
                "not exist",
                "does not exist",
                "not configured",
                "not bound",
                "has not been configured",
            ]

            for phrase in idempotent_success_phrases:
                if phrase in lowered:
                    ok(
                        message="VLAN binding already absent on OLT. Treated as successful.",
                        commands=commands,
                        output=final_output,
                        meta={
                            "host": host,
                            "ssh_port": ssh_port,
                            "action": action,
                            "vlan_id": vlan_id,
                            "vlan_type": vlan_type,
                            "frame": frame,
                            "slot": slot,
                            "control_board": f"{frame}/{slot}",
                            "port_no": port_no,
                            "dry_run": False,
                            "save_config": save_config,
                            "idempotent": True,
                            "prompt_before": prompt_before,
                            "prompt_after_config": prompt_after_config
                        }
                    )

            if "error:" in lowered and "not" not in lowered:
                fail(
                    "Failed to unbind VLAN from control board port on OLT.",
                    extra={
                        "commands": commands,
                        "output": final_output
                    }
                )

        ok(
            message=f"Control board VLAN {action} completed successfully.",
            commands=commands,
            output=final_output,
            meta={
                "host": host,
                "ssh_port": ssh_port,
                "action": action,
                "vlan_id": vlan_id,
                "vlan_type": vlan_type,
                "frame": frame,
                "slot": slot,
                "control_board": f"{frame}/{slot}",
                "port_no": port_no,
                "dry_run": False,
                "save_config": save_config,
                "idempotent": False,
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