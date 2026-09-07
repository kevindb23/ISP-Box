import json
import re
import sys
from netmiko import ConnectHandler


def classify_board(board_name: str) -> str:
    name = (board_name or "").upper()

    if "CSHF" in name or "CGHF" in name:
        return "GPON"

    if "MPSA" in name:
        return "CONTROL"

    if "PISB" in name:
        return "SERVICE"

    return "OTHER"


def normalize_status(raw: str) -> str:
    value = (raw or "").strip().replace("_", " ")
    value = re.sub(r"\s+", " ", value)
    return value if value else "UNKNOWN"


def board_is_importable(board_status: str) -> bool:
    """
    Import rules:
    - any status containing FAILED => NOT importable
    - any status containing NORMAL => importable
    - any status containing ACTIVE => importable
    - any status containing STANDBY and NORMAL => importable
    """
    status = normalize_status(board_status).upper()

    if "FAILED" in status:
        return False

    if "NORMAL" in status:
        return True

    if "ACTIVE" in status:
        return True

    return False


def parse_board_inventory(output: str, frame: int = 0):
    boards = []

    for line in output.splitlines():
        line = line.strip()

        # Examples:
        # 1   H906CGHF   Failed
        # 2   H902CSHF   Normal
        # 3   H901MPSA   Active_normal
        # 4   H901MPSA   Standby_normal
        m = re.match(r'^(\d+)\s+([A-Z0-9]+)\s+([A-Za-z_]+(?:\s+[A-Za-z_]+)*)$', line)
        if not m:
            continue

        slot = int(m.group(1))
        board_name = m.group(2).strip()
        board_status = normalize_status(m.group(3).strip())

        boards.append({
            "frame": frame,
            "slot": slot,
            "board_name": board_name,
            "board_status": board_status,
            "board_type": classify_board(board_name),
            "importable": board_is_importable(board_status),
        })

    return boards


def parse_gpon_board(output: str, frame: int, slot: int, board_name: str, board_status: str):
    ports = []

    # Example:
    # 0     GPON        0              20             Online
    gpon_port_pattern = re.compile(
        r'^\s*(\d+)\s+GPON\s+\d+\s+\d+\s+([A-Za-z\-]+)\s*$',
        re.MULTILINE
    )

    # Example:
    # In port 0/ 2/0 , the total of ONTs are:   1, online:   1
    ont_count_map = {}
    ont_count_pattern = re.compile(
        rf'In port\s+{frame}/\s*{slot}/\s*(\d+)\s*,\s*the total of ONTs are:\s*(\d+),\s*online:\s*(\d+)',
        re.IGNORECASE
    )

    for m in ont_count_pattern.finditer(output):
        port_no = int(m.group(1))
        ont_count_map[port_no] = {
            "ont_count": int(m.group(2)),
            "ont_online": int(m.group(3)),
        }

    for m in gpon_port_pattern.finditer(output):
        port_no = int(m.group(1))
        optic_status = m.group(2).strip()

        ont_info = ont_count_map.get(port_no, {"ont_count": 0, "ont_online": 0})

        ports.append({
            "frame": frame,
            "slot": slot,
            "port": port_no,
            "port_path": f"{frame}/{slot}/{port_no}",
            "board_name": board_name,
            "board_status": board_status,
            "board_type": "GPON",
            "port_type": "GPON",
            "link_status": "online" if optic_status.lower() == "online" else "offline",
            "optic_status": optic_status,
            "speed": None,
            "duplex": None,
            "active_state": None,
            "ont_count": ont_info["ont_count"],
            "ont_online": ont_info["ont_online"],
            "importable": board_is_importable(board_status),
        })

    return ports


def parse_control_board(output: str, frame: int, slot: int, board_name: str, board_status: str):
    ports = []

    for raw_line in output.splitlines():
        line = raw_line.strip()
        if not line:
            continue

        # Expected examples may look like:
        # 0 10GE normal 10000 auto full off active online
        # 1 10GE normal 10000 auto full off active online
        # 2 GE normal 1000 auto full off active online
        # 3 GE normal 1000 auto full off active online
        #
        # Or some devices may present slight spacing differences.

        tokens = re.split(r'\s+', line)
        if len(tokens) < 8:
            continue

        if not re.match(r'^\d+$', tokens[0]):
            continue

        port_no = int(tokens[0])
        port_type = tokens[1].upper()

        if port_type not in ["10GE", "GE", "XGE", "ETH"]:
            continue

        optic_status = tokens[2] if len(tokens) > 2 else None
        speed = tokens[3] if len(tokens) > 3 else None
        auto_neg = tokens[4] if len(tokens) > 4 else None
        duplex = tokens[5] if len(tokens) > 5 else None
        fec = tokens[6] if len(tokens) > 6 else None
        active_state = tokens[7] if len(tokens) > 7 else None
        link_status = tokens[8] if len(tokens) > 8 else None

        ports.append({
            "frame": frame,
            "slot": slot,
            "port": port_no,
            "port_path": f"{frame}/{slot}/{port_no}",
            "board_name": board_name,
            "board_status": board_status,
            "board_type": "CONTROL",
            "port_type": port_type,
            "link_status": link_status,
            "optic_status": optic_status,
            "speed": speed,
            "duplex": duplex,
            "active_state": active_state,
            "auto_neg": auto_neg,
            "fec": fec,
            "ont_count": None,
            "ont_online": None,
            "importable": board_is_importable(board_status),
        })

    return ports


def fetch_ports(host, username, password, frame=0):
    device = {
        "device_type": "huawei",
        "host": host,
        "username": username,
        "password": password,
        "fast_cli": False,
        "global_delay_factor": 2,
    }

    result = {
        "success": True,
        "host": host,
        "frame": frame,
        "boards": [],
        "ports": [],
        "port_count": 0
    }

    conn = None

    try:
        conn = ConnectHandler(**device)
        conn.enable()

        inventory_output = conn.send_command_timing(
            f"display board {frame}",
            strip_prompt=False,
            strip_command=False
        )

        boards = parse_board_inventory(inventory_output, frame=frame)
        result["boards"] = boards

        for board in boards:
            slot = board["slot"]
            board_name = board["board_name"]
            board_status = board["board_status"]
            board_type = board["board_type"]

            detail_output = conn.send_command_timing(
                f"display board {frame}/{slot}",
                strip_prompt=False,
                strip_command=False
            )

            lowered = detail_output.lower()
            if "failure:" in lowered or "does not exist" in lowered:
                continue

            if board_type == "GPON":
                result["ports"].extend(
                    parse_gpon_board(detail_output, frame, slot, board_name, board_status)
                )

            elif board_type == "CONTROL":
                result["ports"].extend(
                    parse_control_board(detail_output, frame, slot, board_name, board_status)
                )

        result["port_count"] = len(result["ports"])
        return result

    except Exception as e:
        return {
            "success": False,
            "error": str(e)
        }

    finally:
        if conn:
            conn.disconnect()


def main():
    raw = sys.stdin.read()
    if not raw.strip():
        print(json.dumps({
            "success": False,
            "error": "Missing JSON payload."
        }))
        return

    try:
        data = json.loads(raw)
    except Exception:
        print(json.dumps({"success": False, "error": "Invalid JSON payload."}))
        return

    host = str(data.get("host", "")).strip()
    username = str(data.get("username", "")).strip()
    password = str(data.get("password", "")).strip()
    frame = int(data.get("frame", 0))

    if not host or not username or not password:
        print(json.dumps({"success": False, "error": "OLT connection details are incomplete."}))
        return

    result = fetch_ports(host, username, password, frame)
    print(json.dumps(result))


if __name__ == "__main__":
    main()
