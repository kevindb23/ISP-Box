# S-VLAN QinQ CRUD Design

## Goal

Make S-VLAN records manage their related Huawei QinQ configuration through the existing VLAN module, including safe create, read, update, and delete behavior.

## Current gap

The existing S-VLAN deployment creates `vlan <id> smart`, applies `vlan attrib <id> q-in-q`, and sets `vlan forwarding <id> vlan-connect`. The delete path does not remove the associated `port vlan <id> <frame>/<slot> <port>` binding and cannot represent the VLAN range and QinQ lines present on the MA5800-X2 configuration.

## Design

Extend the existing S-VLAN flow rather than adding a separate module. The S-VLAN record remains the source of ownership for its VLAN number and OLT port. Deployment and deletion scripts will generate deterministic Huawei commands from that record. Deletion will remove the OLT port binding before removing forwarding, QinQ attributes, and the VLAN; the local row is deleted only after the OLT verification confirms that the VLAN no longer exists. Existing child C-VLAN records continue to block deletion.

The VLAN page will add a QinQ configuration view that exposes the effective S-VLAN-related commands and their deployment state, while the existing S-VLAN CRUD actions remain the primary entry point. Existing C-VLAN and MGMT-VLAN behavior is preserved.

## Safety and compatibility

- Do not print or persist device credentials in command output.
- Treat an already-absent OLT VLAN as an idempotent delete success.
- Do not remove shared or unrelated VLAN-range commands automatically.
- Reject deletion when child C-VLAN records exist.
- Preserve BNG cleanup after successful OLT cleanup.

## Verification

Add contract tests for command generation, route/UI wiring, dependency protection, and script syntax. Run the repository PHP syntax checks, Python compilation, JavaScript syntax check, diff check, and the existing VLAN integration contract.
