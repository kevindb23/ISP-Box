#!/usr/bin/env python3
import json
import sys
import re
from netmiko import ConnectHandler


def fail(message, extra=None):
    payload = {
        "ok": False,
        "message": message
    }
    if extra is not None:
        payload["extra"] = extra
    print(json.dumps(payload))
    sys.exit(1)


def has_cli_error(output: str) -> bool:
    text = output or ""

    benign_patterns = [
        r"Make configuration repeatedly",
        r"already exists",
        r"Service virtual port has existed already",
    ]

    for pattern in benign_patterns:
        if re.search(pattern, text, re.IGNORECASE):
            return False

    bad_patterns = [
        r"% Unknown command",
        r"Error:",
        r"Failure:",
        r"parameter.+invalid",
        r"too many parameters",
        r"incomplete command",
        r"does not exist",
    ]

    for pattern in bad_patterns:
        if re.search(pattern, text, re.IGNORECASE):
            return True

    return False


def run_cmd(conn, cmd: str, allow_fail: bool = False):
    output = conn.send_command_timing(
        cmd,
        strip_prompt=False,
        strip_command=False
    )

    if not allow_fail and has_cli_error(output):
        raise RuntimeError(f"Command failed: {cmd} | Output: {output}")

    return output


def extract_onts(output: str):
    items = []

    for line in output.splitlines():
        m = re.match(
            r"^\s*(\d+/\s*\d+/\s*\d+)\s+(\d+)\s+([0-9A-F]{8,20})\s+",
            line,
            re.IGNORECASE
        )

        if m:
            items.append({
                "fsp": m.group(1).replace(" ", ""),
                "ont_id": int(m.group(2)),
                "serial": m.group(3).upper().strip(),
            })

    return items


def find_existing_ont_id(output: str, serial: str):
    serial = serial.upper().strip()

    for item in extract_onts(output):
        if item["serial"] == serial:
            return item["ont_id"]

    return None


def find_first_free_ont_id(output: str) -> int:
    used_ids = sorted({item["ont_id"] for item in extract_onts(output)})

    for i in range(0, 256):
        if i not in used_ids:
            return i

    raise RuntimeError("No available ONT ID on this PON port")


def extract_conflicted_service_port(output: str):
    m = re.search(
        r"Conflicted service virtual port index:\s*(\d+)",
        output or "",
        re.IGNORECASE
    )

    if not m:
        return None

    return int(m.group(1))


def service_port_exists(conn, service_port_id: int) -> bool:
    out = run_cmd(conn, f"display service-port {service_port_id}", allow_fail=True)

    if re.search(rf"\b{service_port_id}\b", out) and "Failure:" not in out:
        return True

    return False


def create_service_port_idempotent(conn, cmd: str, requested_service_port: int, outputs: list):
    out = run_cmd(conn, cmd, allow_fail=True)
    outputs.append({
        "command": cmd,
        "output": out
    })

    conflicted_id = extract_conflicted_service_port(out)

    if conflicted_id:
        outputs.append({
            "command": "service-port conflict",
            "output": (
                f"Requested service-port {requested_service_port} conflicts with "
                f"existing service-port {conflicted_id}. Reusing existing service-port."
            )
        })
        return conflicted_id

    if has_cli_error(out):
        raise RuntimeError(f"Command failed: {cmd} | Output: {out}")

    return requested_service_port


def main():
    if len(sys.argv) != 2:
        fail("Usage: python3 ztp_provision_ont.py '<json_payload>'")

    try:
        data = json.loads(sys.argv[1])
    except Exception as e:
        fail(f"Invalid JSON payload: {e}")

    required = [
        "host", "username", "password",
        "frame", "slot", "port", "sn",
        "svlan", "cvlan",
        "lineprofile_id", "srvprofile_id",
        "tr069_profile_id", "internet_wan_profile_id", "tr069_wan_profile_id",
        "tr069_vlan",
        "internet_service_port", "tr069_service_port"
    ]

    missing = [k for k in required if data.get(k) in (None, "")]
    if missing:
        fail("Missing required payload fields", {"missing": missing})

    device = {
        "device_type": "huawei_olt",
        "host": data["host"],
        "username": data["username"],
        "password": data["password"],
        "port": int(data.get("ssh_port", 22)),
        "fast_cli": False,
        "global_delay_factor": 2,
    }

    frame = int(data["frame"])
    slot = int(data["slot"])
    port = int(data["port"])
    sn = str(data["sn"]).upper().strip()

    svlan = int(data["svlan"])
    cvlan = int(data["cvlan"])

    lineprofile_id = int(data["lineprofile_id"])
    srvprofile_id = int(data["srvprofile_id"])

    tr069_profile_id = int(data["tr069_profile_id"])
    internet_wan_profile_id = int(data["internet_wan_profile_id"])
    tr069_wan_profile_id = int(data["tr069_wan_profile_id"])
    tr069_vlan = int(data["tr069_vlan"])

    requested_internet_service_port = int(data["internet_service_port"])
    requested_tr069_service_port = int(data["tr069_service_port"])

    actual_internet_service_port = requested_internet_service_port
    actual_tr069_service_port = requested_tr069_service_port

    conn = None
    outputs = []

    try:
        conn = ConnectHandler(**device)
        conn.enable()

        out = run_cmd(conn, "config")
        outputs.append({"command": "config", "output": out})

        out = run_cmd(conn, f"interface gpon {frame}/{slot}")
        outputs.append({"command": f"interface gpon {frame}/{slot}", "output": out})

        show_cmd = f"display ont info {port} all"
        ont_info_out = run_cmd(conn, show_cmd)
        outputs.append({"command": show_cmd, "output": ont_info_out})

        existing_ont_id = find_existing_ont_id(ont_info_out, sn)

        if existing_ont_id is not None:
            ont_id = existing_ont_id
            outputs.append({
                "command": "ont lookup",
                "output": f"Existing ONT found for serial {sn}, reusing ont_id {ont_id}"
            })
        else:
            ont_id = find_first_free_ont_id(ont_info_out)
            desc = f"SUB_{svlan}_{cvlan}"
            add_cmd = (
                f'ont add {port} {ont_id} sn-auth "{sn}" omci '
                f'ont-lineprofile-id {lineprofile_id} '
                f'ont-srvprofile-id {srvprofile_id} '
                f'desc "{desc}"'
            )
            out = run_cmd(conn, add_cmd)
            outputs.append({"command": add_cmd, "output": out})

        interface_commands = [
            f"ont ipconfig {port} {ont_id} pppoe vlan {cvlan} priority 0 user-account ont-input",
            f"ont ipconfig {port} {ont_id} ip-index 1 dhcp vlan {tr069_vlan} priority 1",
            f"ont tr069-server-config {port} {ont_id} profile-id {tr069_profile_id}",
            f"ont internet-config {port} {ont_id} ip-index 0",
            f"ont wan-config {port} {ont_id} ip-index 0 profile-id {internet_wan_profile_id}",
            f"ont wan-config {port} {ont_id} ip-index 1 profile-id {tr069_wan_profile_id}",
            f"ont port native-vlan {port} {ont_id} eth 1 vlan {cvlan} priority 0",
            f"ont port native-vlan {port} {ont_id} iphost vlan {tr069_vlan} priority 1",
        ]

        for cmd in interface_commands:
            out = run_cmd(conn, cmd, allow_fail=True)
            outputs.append({"command": cmd, "output": out})

            if has_cli_error(out):
                raise RuntimeError(f"Command failed: {cmd} | Output: {out}")

        out = run_cmd(conn, "quit")
        outputs.append({"command": "quit", "output": out})

        internet_sp_cmd = (
            f"service-port {requested_internet_service_port} vlan {svlan} "
            f"gpon {frame}/{slot}/{port} ont {ont_id} gemport 1 "
            f"multi-service user-vlan {cvlan} tag-transform default"
        )

        tr069_sp_cmd = (
            f"service-port {requested_tr069_service_port} vlan {tr069_vlan} "
            f"gpon {frame}/{slot}/{port} ont {ont_id} gemport 2 "
            f"multi-service user-vlan {tr069_vlan} tag-transform translate"
        )

        if service_port_exists(conn, requested_internet_service_port):
            outputs.append({
                "command": f"display service-port {requested_internet_service_port}",
                "output": f"Service-port {requested_internet_service_port} already exists, skipping creation"
            })
        else:
            actual_internet_service_port = create_service_port_idempotent(
                conn,
                internet_sp_cmd,
                requested_internet_service_port,
                outputs
            )

        if service_port_exists(conn, requested_tr069_service_port):
            outputs.append({
                "command": f"display service-port {requested_tr069_service_port}",
                "output": f"Service-port {requested_tr069_service_port} already exists, skipping creation"
            })
        else:
            actual_tr069_service_port = create_service_port_idempotent(
                conn,
                tr069_sp_cmd,
                requested_tr069_service_port,
                outputs
            )

        print(json.dumps({
            "ok": True,
            "message": "Provisioning successful",
            "data": {
                "ont_id": ont_id,
                "svlan": svlan,
                "cvlan": cvlan,
                "lineprofile_id": lineprofile_id,
                "srvprofile_id": srvprofile_id,
                "internet_service_port": actual_internet_service_port,
                "tr069_service_port": actual_tr069_service_port,
                "requested_internet_service_port": requested_internet_service_port,
                "requested_tr069_service_port": requested_tr069_service_port,
                "existing_ont_reused": existing_ont_id is not None,
                "commands": outputs
            }
        }))
        sys.exit(0)

    except Exception as e:
        fail(str(e), {"commands": outputs})

    finally:
        if conn:
            try:
                conn.disconnect()
            except Exception:
                pass


if __name__ == "__main__":
    main()