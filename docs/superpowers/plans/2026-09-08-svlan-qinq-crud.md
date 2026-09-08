# S-VLAN QinQ CRUD Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend S-VLAN CRUD so related Huawei QinQ configuration is displayed and safely removed from the OLT before deleting the local record.

**Architecture:** Keep `network_vlans` as the ownership record and extend the existing VLAN management service, scripts, repository, and page. The Python script will generate ordered, idempotent cleanup commands; the PHP service will enforce child-C-VLAN protection and only delete the database row after OLT verification.

**Tech Stack:** Plain PHP, PDO, Huawei Netmiko Python scripts, server-rendered HTML, module-scoped JavaScript, repository contract tests.

**Spec:** `docs/superpowers/specs/2026-09-08-svlan-qinq-crud-design.md`

## Global Constraints

- Preserve existing C-VLAN and MGMT-VLAN behavior.
- Never expose device credentials in UI, logs, or test fixtures.
- Delete local records only after confirmed OLT cleanup.
- Keep child C-VLAN ownership protection.
- Use existing NexusBox `nx-*` UI and API conventions.

---

### Task 1: Lock down QinQ cleanup behavior with failing tests

**Files:**
- Modify: `tests/Architecture/vlan_bng_integration_contract_test.php`
- Test: `app/Modules/VlanManagement/Scripts/delete_vlan.py`

- [ ] **Step 1: Add assertions for S-VLAN QinQ cleanup commands**

Assert that the delete script contains ordered removal of the S-VLAN port binding, forwarding configuration, QinQ attribute, and VLAN record, plus idempotent verification markers.

- [ ] **Step 2: Add assertions for QinQ UI/API wiring**

Assert that the VLAN view has a QinQ tab/table container and the JavaScript exposes S-VLAN QinQ command details without removing the existing S-VLAN actions.

- [ ] **Step 3: Run the focused test and verify it fails**

Run: `php tests/Architecture/vlan_bng_integration_contract_test.php`

Expected: FAIL because the current S-VLAN delete script and view do not provide the complete QinQ cleanup contract.

### Task 2: Implement ordered S-VLAN QinQ cleanup

**Files:**
- Modify: `app/Modules/VlanManagement/Scripts/delete_vlan.py`
- Modify: `app/Modules/VlanManagement/Services/VlanManagementService.php`

- [ ] **Step 1: Pass the stored OLT port coordinates to deletion**

For S-VLAN rows, include `frame`, `slot`, and `port` from the joined OLT port record in the script payload.

- [ ] **Step 2: Generate ordered S-VLAN cleanup commands**

Generate `undo port vlan <id> <frame>/<slot> <port>` when a port is owned by the S-VLAN, then remove forwarding, QinQ attributes, and the VLAN. Keep C-VLAN and MGMT-VLAN command generation unchanged.

- [ ] **Step 3: Make already-absent state idempotent**

Recognize Huawei responses indicating that a VLAN, attribute, forwarding entry, or port binding is already absent as successful cleanup when final verification confirms absence.

- [ ] **Step 4: Run the focused script and PHP tests**

Run: `python3 -m py_compile app/Modules/VlanManagement/Scripts/delete_vlan.py` and `php tests/Architecture/vlan_bng_integration_contract_test.php`.

Expected: PASS.

### Task 3: Add the QinQ view and CRUD command visibility

**Files:**
- Modify: `app/Modules/VlanManagement/Views/index.php`
- Modify: `app/Modules/VlanManagement/Assets/js/VlanManagement.js`
- Modify: `app/Modules/VlanManagement/Assets/css/VlanManagement.css` if present; otherwise use existing scoped styles only.

- [ ] **Step 1: Add the QinQ tab and table container**

Add a fourth VLAN tab labelled `Q-in-Q` with an accessible tab label and a responsive table container.

- [ ] **Step 2: Render effective S-VLAN QinQ rows**

Derive rows from S-VLAN records and display OLT, S-VLAN, OLT port, QinQ attribute, forwarding mode, deployment state, and the exact non-secret commands that will be applied or removed.

- [ ] **Step 3: Connect CRUD actions to existing endpoints**

Keep create/edit/delete actions backed by the existing S-VLAN endpoints. Show deletion as a single S-VLAN/QinQ operation and preserve child-C-VLAN error messages.

- [ ] **Step 4: Run JavaScript and contract checks**

Run: `node --check app/Modules/VlanManagement/Assets/js/VlanManagement.js` and `php tests/Architecture/vlan_bng_integration_contract_test.php`.

Expected: PASS.

### Task 4: Full verification and implementation note

**Files:**
- Create: `public/assets/.doc/2026-09-08-svlan-qinq-crud.md`

- [ ] **Step 1: Run repository syntax and diff checks**

Run: `find app framework bootstrap config public -name '*.php' -print0 | xargs -0 -n1 php -l`, `git diff --check`, and `python3 -m py_compile app/Modules/VlanManagement/Scripts/*.py`.

- [ ] **Step 2: Run relevant architecture tests**

Run: `php tests/Architecture/vlan_bng_integration_contract_test.php`, `php tests/Architecture/route_contract_test.php`, and `php tests/Architecture/frontend_modal_contract_test.php`.

- [ ] **Step 3: Write the verification note**

Record changed files, command ordering, dependency safeguards, checks run, and any live-OLT verification that was not possible in the local checkout.

- [ ] **Step 4: Inspect final status**

Run: `git status --short --branch` and leave unrelated `public/assets/.doc/errors.txt` untouched.
