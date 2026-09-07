# NexusBox QA Failures Master Implementation Plan

**Goal:** Resolve the seven root problems identified by the 276-check Admin, Subscriber, Technician, and authentication/API retest, then prove closure with a clean sequential Playwright run.

**Architecture:** Stabilize backend contracts first, then notification authorization, then the shared navigation/accessibility shell, then deployment hardening. Keep fixes module-local where possible and use shared boundaries only for shared defects.

**Constraints:** Existing production records must not be changed. All fixtures use unique `pw-qa-<timestamp>` names, verify ownership before deletion, and confirm old IDs remain. Do not test charges, network deployment, payment, or global maintenance settings in regression. Never commit credentials, cookies, tokens, raw response bodies, or secrets.

## Root-cause backlog

| ID | Priority | Root problem | Evidence | Closure target |
|---|---|---|---|---|
| R1 | High | Technician work-orders page returns HTTP 500 | PORTALS-0081/82; AUTH-022/24 | Page/API load with no uncaught 4xx/5xx |
| R2 | High | HTTP login is not redirected to HTTPS | AUTH-025 | Deferred for the current private-IP deployment; enforce after a domain-backed TLS endpoint is available |
| R3 | High | Plan CRUD response does not match persistence | ADMIN-094/96/97/98 | Create/update/delete return truthful status and clean up |
| R4 | Medium | Invalid CSRF returns HTTP 500 | AUTH-034/35/47/48/60/61 | Missing/invalid CSRF returns consistent 419 or 403 JSON |
| R5 | Medium | Technician notification requests return 403 | PORTALS-0076/88 | Portal feed is authorized and maintenance-only |
| R6 | Medium | Compact/mobile sidebar bell and menu state fail | ADMIN-107; PORTALS-0066/97 | All icons and bell are visible/reachable at 390px |
| R7 | Low | Six admin pages contain unnamed controls | ADMIN-007/43/46/52/55/70 | Zero unnamed enabled comboboxes/switches |

## Ordered implementation workstreams

### 1. Reproduce and instrument work-orders failure

Inspect `app/Modules/TechnicianPortal/Controllers/TechnicianPortalController.php`, `TechnicianPortalApiController.php`, its services/repositories, and `app/Modules/WorkOrders/`. Re-run the route as technician and admin with protected server logging to identify the exact failing query, dependency, DTO, or missing related record. Add `tests/Architecture/technician_work_orders_contract_test.php` and preserve the failing Playwright assertion before changing behavior.

### 2. Repair work-orders loading and error boundaries

Fix the dependency/query/DTO issue found in Workstream 1. Preserve the empty state when no assignments exist. Catch expected domain/database errors at the controller boundary, log a correlation-safe server diagnostic, and render a controlled application error state without exception details. Test empty list, assigned list, invalid ID, unauthorized role, keyboard focus, and 390px/768px layouts.

### 3. Make subscriber-plan CRUD transactional

Inspect `app/Modules/SubscriberPlans/Controllers/SubscriberPlansApiController.php`, its service/repository, validators, and RADIUS synchronization. Define one canonical payload and response contract. Validate required name, numeric price/speed, plan type, and duplicate names. Commit plan and RADIUS changes consistently, or compensate/rollback on failure. Return 201/200 only after commit, 422 for validation, and controlled 409/500 only when nothing was committed. Extend `tests/Playwright/admin/crud.mjs` and add `tests/Architecture/subscriber_plan_crud_contract_test.php` for create/read/UI/update/reload/delete/old-record preservation.

### 4. Normalize CSRF failures

Trace the exception path through `app/Core/Security/Csrf.php`, request middleware/router, and the shared API response helper. Map missing or invalid tokens to JSON HTTP 419 or 403 with `success:false` and `Invalid or expired CSRF token.` before controller validation. Add architecture and Playwright checks for missing token, wrong token, valid token with invalid ID, and validation failures across all three roles. Confirm no SQL/PDO/stack details escape.

### 5. Define notification authorization

Inspect `app/Modules/Notifications/`, SubscriberPortal, TechnicianPortal, notification permissions, and `public/assets/js/nx.js`. Enforce this matrix: admin may see security and maintenance events; subscriber and technician feeds may see only scheduled-downtime/system-maintenance events. Unauthorized event types must be filtered, not cause a drawer error. Verify notification read state, persistent unread count, zero-badge omission, modal opening, and cross-role isolation.

### 6. Implement explicit sidebar states

Modify `public/assets/js/nx.js`, `resources/css/app.css`, `app/UI/Views/layouts/sidebar.php`, and `app/UI/Views/layouts/app.php` as needed. Model expanded, compact rail, mobile drawer open, and mobile drawer closed explicitly. Section accordions may hide labels/items only in expanded mode; compact mode must render every authorized item as an icon regardless of `hidden` or `is-collapsed-item`. Remove conflicting late rules and disable accordion opacity/max-height transitions in compact mode. Add `tests/Playwright/sidebar-compact-rail.mjs` at 1440px, 768px, and 390px, including bell bounds, keyboard focus, and modal-over-sidebar z-index.

### 7. Repair accessibility labels

Update the affected Plans, Payment Gateway, Tickets, Technician Management, Work Orders, and API Tokens views/components. Associate every select with a visible label or `aria-labelledby`; give each switch an explicit name describing its setting. Add `tests/Playwright/accessibility-controls.mjs` for accessible names, tab order, visible focus, Enter/Space activation, and error association.

### 8. Enforce secure transport (deployment-gated)

The application includes an opt-in `APP_FORCE_HTTPS` redirect for domain-backed deployments. Do not enable it on the current private-IP HTTP instance because no trusted certificate/TLS endpoint is available and forcing it would make the application unreachable. The QA suite records this as an explicit private-IP deployment exception. When a domain and certificate are provisioned, configure the reverse proxy first, set `APP_FORCE_HTTPS=true`, then verify HTTPS, HSTS, Secure/HttpOnly/SameSite cookies, no mixed content, and protected-route behavior.

### 9. Expand safe fixtures and close regression

Add cleanup-safe ticket and work-order fixtures under `tests/Playwright/fixtures/`; do not guess existing IDs. Run each account sequentially to avoid session invalidation. Repeat the baseline 276 checks plus new tests, require successful cleanup and old-record preservation, regenerate JSON/screenshots/PDF, and review every remaining BLOCKED item.

## Release gate

Release remains blocked until work-orders load cleanly; Plan CRUD status matches persistence; CSRF failures are 419/403 rather than 500; portal feeds contain only authorized maintenance notices; compact navigation shows all icons and a reachable bell; affected controls have accessible names; and ticket/work-order CRUD has safe fixture coverage or an explicitly approved scope exception. HTTPS is a deployment gate and remains an accepted exception only for the current private-IP HTTP environment.

## Verification commands

```bash
node --check public/assets/js/nx.js
git diff --check
QA_ADMIN_USER=... QA_ADMIN_PASSWORD=... QA_SUBSCRIBER_USER=... QA_SUBSCRIBER_PASSWORD=... QA_TECHNICIAN_USER=... QA_TECHNICIAN_PASSWORD=... node tests/Playwright/retest-admin.mjs
QA_ADMIN_USER=... QA_ADMIN_PASSWORD=... QA_SUBSCRIBER_USER=... QA_SUBSCRIBER_PASSWORD=... QA_TECHNICIAN_USER=... QA_TECHNICIAN_PASSWORD=... node tests/Playwright/retest-portals.mjs
QA_ADMIN_USER=... QA_ADMIN_PASSWORD=... QA_SUBSCRIBER_USER=... QA_SUBSCRIBER_PASSWORD=... QA_TECHNICIAN_USER=... QA_TECHNICIAN_PASSWORD=... QA_SECURITY_PROBES_READY=1 node tests/Playwright/retest-security.mjs
```

The final report must contain zero unexplained failures, no unsafe fixture residue, and sanitized evidence only.
