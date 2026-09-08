# S-VLAN QinQ CRUD Implementation

## Applied behavior

S-VLAN deletion now reads the OLT configuration before changing it. It discovers service-port references, OLT port bindings, QinQ VLAN ranges, QinQ attributes, and forwarding entries. It removes dependencies for the selected S-VLAN first, removes the VLAN range when necessary, and recreates remaining VLANs in that range before verifying the target VLAN is absent.

The Q-in-Q tab displays the S-VLAN-owned QinQ commands and uses the existing S-VLAN create, edit, retry, and delete operations. Child C-VLAN records still prevent parent S-VLAN deletion.

## Verification

- VLAN integration contract: passed.
- Route contract: passed.
- Frontend modal contract: passed.
- PHP, Python, and JavaScript syntax checks: passed.
- Vue production build: passed.
- Live OLT commands were not executed during validation.
