# NexusBox second-pass data-contract matrix

Audit date: 2026-08-15. Statuses describe the database-to-DOM contract, not live connectivity to external equipment.

| Module | Page/detail interface | Data source and contract | Status | Verification / resolution |
|---|---|---|---|---|
| Api | v1 authentication and subscriber mutations | `api_tokens`, `users`, Subscribers service | FIXED | Removed duplicate middleware and invalid orphan controller methods; global authentication now supplies scoped identity. |
| Api | System identity | `system_config`, `branding` → `/api/v1/system/info` | FIXED | Persistent `instance_id`; safe fields only. |
| Api | Monitoring summaries | Subscribers, sessions, provisioning, ONTs, OLTs, invoices | FIXED | Intentional aggregate contracts; no credentials or raw database rows. OLT online/offline remains null because the schema has no trustworthy live-health field. |
| ApiTokens | Token list/create/revoke | `api_tokens` → Entity → Service → shared response → modal/table | FIXED | Name, description, purpose, scopes, creator, expiry, usage and revocation metadata. Raw token appears once. |
| Audit | Log table/detail data | `audit_logs` → repository/entity → table | PASS | Central trail covers all registered mutations and sensitive reads; nested secrets redacted. |
| Auth | Login | `users`, login attempts → Auth service → session/API response | PASS | Browser and API responses use centralized security boundary; login side effects audited. |
| Billing | Overview, invoice/payment/adjustment/run details | Billing repositories and explicit relationship queries → seven UI dialogs | PASS | Authenticated page and invoice-detail modal loaded with subscriber, items, totals, payment and adjustment relationships. Shared API client now used. |
| Branding | Branding form/profile | `branding` → DTO/validator/service → form | PASS | Authenticated GET page loaded; mutation contract centrally audited. |
| CgnatManagement | BNG, pools, preview/runtime | CGNAT/BNG repositories → service → tabs/modals | PARTIAL | Page/data contract loads; external BNG execution still requires live device testing. Shared response layer applied. |
| Dashboard | Cards, traffic/table | Dashboard aggregate queries → DTO/entity → widgets | PASS | Authenticated page loaded without visible error; no detail modal contract. |
| NapManagement | Logical/map/planner, box details | Network boxes, splitters, output ports, uplinks, ODF/LCP relationships | PARTIAL | Page contracts loaded and external reverse geocoder is the sole documented direct fetch; physical topology actions require device/field data. |
| OltManagement | Device/port/profile/CLI preview dialogs | OLT repositories → service → shared API → runtime dialogs | PARTIAL | Device detail loaded. Device password remains visible only under the explicit user exception. Live CLI/apply verification requires Huawei OLT. |
| OntDevices | Inventory/discovery/ACS details | ONT inventory, autofind, ACS and optical data | PARTIAL | Inventory detail loaded and distinguishes absent ACS/optical data; polling and ACS actions require live OLT/GenieACS. |
| PaymentGateway | Settings and transaction table | Gateway settings/transactions → DTO/entity → page | PARTIAL | Authenticated page loaded; external PayMongo callback completion remains a live integration test. |
| Radius | Settings modal/table | `radius_settings` → redacting entity/service → modal | PARTIAL | Page loads; credentials are not emitted in list contracts. Live connection/session testing requires FreeRADIUS. |
| ServiceProvisioning | Selection, validation and job details | Jobs/bindings plus OLT/ONT/NAP/VLAN joins | PARTIAL | Page, readiness controls, activation state, logs and recent-job data load. Read contracts omit PPP/OLT passwords; internal OLT credentials are decrypted only at the execution boundary. Execution/verification requires network devices. |
| StaffAttendance | Attendance cards/tables | Attendance/log repositories → DTO/entity → page | PASS | Page loaded; shared form/API client applied. |
| SubscriberPlans | Plan table/create/edit dialogs | `plans` → DTO/validator/entity → table/modals | PASS | Authenticated page loaded with two dialogs and no visible error. |
| SubscriberPortal | Profile, services, invoices, payments, tickets | Ownership-scoped portal repository → entities → panes/details | PASS | Shared API client applied; ownership and role contract retained. |
| Subscribers | Registry table | Subscribers + preferred service + plan + session state | FIXED | Removed stale persisted search/results; live table returns all four current subscribers. Multiple services are counted and preferred deterministically. |
| Subscribers | View → Provisioning Information | Bindings/latest job → OLT/PON/ONT/NAP/splitter/VLAN/ACS/session | FIXED | Replaced obsolete `subscriber_provisioning` join. Live John Doe modal displays complete supported data and semantic empty states. |
| Subscribers | PPP credentials | `subscriber_services` | SECURITY REDACTED | PPP username remains operationally visible; PPP password removed from read API, Entity and detail modal. Reset/create output remains one-time workflow output. |
| SystemSettings | General settings page | `system_config` → module repository/entity/DTO/validator/service | PASS | Independent module and authenticated page verified. Persistent instance identity is not editable through the general form. |
| TechnicianManagement | Dashboard and technician detail | Users, profiles, attendance and work orders | PASS | Detail modal loaded profile, current attendance, assignments and history. Missing optional profile values are explicitly absent. |
| TechnicianPortal | Assigned work-order details/actions | Ownership-scoped work-order repository → entity → page | PASS | Shared API/form client applied; role boundary retained. |
| Tickets | Ticket detail/messages/work-order link | Tickets, subscriber/service, assignee, latest work order, messages | PASS | Authenticated detail modal loaded realistic cross-module QA ticket. Explicit loading/error state exists. |
| Users | User table/create/edit/reset | Users repository → DTO/validator/entity → modal/table | PASS | Authenticated page loaded; password values are never returned by read contracts. |
| VlanManagement | VLAN/pool tables and runtime dialogs | VLAN/pool/OLT-port repositories → service → tables/dialogs | PARTIAL | Three data tables load and shared API client applied; deploy/delete requires live OLT testing. |
| WorkOrders | Work-order detail/tasks/history | Work order + subscriber/service + ticket + technician + logs/tasks | FIXED | Added missing source-ticket mapping. Live modal now shows `TKT-2026-000017`; loading/error/reset contract verified. |

## Contract safeguards

- `subscriber_provisioning_contract_test.php` protects the full Subscribers provisioning field path and password redaction.
- `frontend_modal_contract_test.php` protects critical Subscribers, Tickets and Work Orders modal IDs, fields and state handling.
- `frontend_api_access_contract_test.php` prevents internal modules from bypassing `NX.api`.
- `api_response_contract_test.php` prevents controllers from manually emitting JSON.
- `monitoring_api_contract_test.php` protects routes, identity fields and scope decisions.
- `api_token_integration_contract_test.php` protects hash-only token storage and integration metadata.
- `provisioning_secret_contract_test.php` prevents provisioning read endpoints and UI code from exposing PPP or OLT passwords while preserving internal encrypted-secret handling.
- Live database tests protect the migrated schema, persistent instance ID and aggregate monitoring contracts.
- Authenticated GET-only browser regression covers all 21 administrative routes plus ticket and work-order detail relationships.
