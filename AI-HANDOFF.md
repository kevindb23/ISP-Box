# NexusBox / ISP-in-a-Box — AI Handoff

Last updated: 2026-08-31 (Asia/Manila)  
Application release: `1.0.0`  
Workspace: `/var/www/nexusbox`

## 1. Purpose of this document

This file is the operational and engineering handoff for another AI or developer continuing work on NexusBox. Read it before inspecting or changing the application. It summarizes the architecture, integrations, major completed work, verified live state, deployment procedures, security boundaries, and remaining considerations.

Do not add plaintext passwords, bearer tokens, payment secrets, device credentials, or database credentials to this document. Secrets are intentionally stored outside tracked source files.

## 2. System overview

NexusBox is a modular ISP operations platform covering:

- Subscriber and plan management
- Billing, invoicing, payments, and manual-payment review
- RADIUS and subscriber authentication
- OLT, ONT, ACS/TR-069, VLAN, NAP, ODF, and LCP management
- Subscriber service provisioning
- BNG and Accel-PPP management
- CGNAT/iptables management
- Core Juniper and FRRouting management
- Tickets, work orders, technicians, and attendance
- Subscriber and technician portals
- Users, RBAC, auditing, branding, and system settings
- Privacy-safe infrastructure monitoring delivered to the HQ dashboard

This host is a live integration/test server, not the final production deployment. Treat its databases, network integrations, and device connections as real unless the user explicitly authorizes mutation.

## 3. Runtime and architecture

- Backend: PHP 8.1+ modular application under `app/Modules`
- Core framework: custom router, container, session, controller, and database infrastructure under `framework` and `app/Infrastructure`
- Database: MariaDB through PDO
- Web server: Apache using `mod_php`; PHP-FPM is not installed or monitored
- Legacy frontend build: Vite/Tailwind from the repository root, output in `public/build`
- Modern frontend: Vue 3 + TypeScript + Tailwind in `frontend-next`, output in `public/build-next`
- Protected runtime configuration: `/var/www/nexusbox/.env.runtime.php`
- Canonical application version: `/var/www/nexusbox/VERSION`
- Current canonical version: `1.0.0`

Dependency injection is configured in `bootstrap/container.php`. Repositories participating in one business transaction share the same PDO handle.

## 4. Module inventory

Current modules under `app/Modules`:

- Api
- ApiTokens
- Audit
- Auth
- Billing
- BngManagement
- Branding
- CgnatManagement
- Dashboard
- NapManagement
- OltManagement
- OntDevices
- PaymentGateway
- Radius
- RouterManagement
- ServiceProvisioning
- StaffAttendance
- SubscriberPlans
- SubscriberPortal
- Subscribers
- SystemSettings
- TechnicianManagement
- TechnicianPortal
- Tickets
- Users
- VlanManagement
- WorkOrders

The modern Vue entry point is `frontend-next/src/main.ts`. Module roots use `data-nx-next-root` and are mounted again after SPA navigation through the `nx:page-load` event.

## 5. External systems and current endpoints

- ISP-in-a-Box portal: `http://10.0.10.155`
- HQ monitoring dashboard: `http://10.0.10.180:8080`
- BNG/FRR server: managed through the configurable BNG settings; FRR is accessed using `vtysh` on that host
- Core router: Juniper; management address is configurable in the Routers module
- ACS: configured through runtime/application settings and currently reachable
- RADIUS: external authentication/accounting database and daemon integration
- OLT: one or more configurable device connections
- Payment gateways: test-mode integrations were used during QA

Private HTTP delivery to HQ is currently explicitly authorized. Heartbeats are bearer-authenticated and HMAC-signed but are not encrypted in transit. Migrate to verified HTTPS before exposing the receiver beyond the trusted private network.

## 6. Secret handling

Never print or copy runtime secrets into logs, reports, tests, browser output, source files, or this handoff.

Relevant secret locations and mechanisms:

- Database, CoA, monitoring, and other runtime secrets: `.env.runtime.php` and/or environment variables
- HQ monitoring token: protected runtime monitoring configuration
- Network-device credentials: encrypted through the application secret-cipher mechanism where supported
- API tokens: only SHA-256 hashes are stored; raw tokens are shown once at creation
- SSH known-host state for BNG operations: per-runtime-user secure temporary path, avoiding the former shared runtime permission conflict

Device passwords must never be placed in process command-line arguments. A known historical concern about plaintext OLT-detail presentation was explicitly excluded from one earlier fix scope; re-check current behavior before claiming it is resolved.

## 7. Major completed engineering work

### Architecture and consistency

- Modules were reviewed and progressively aligned around DTOs, validators, entities, repositories, services, controllers, and route boundaries.
- Audit coverage was expanded across operational actions and cross-module workflows.
- System Settings was separated as its own module.
- RBAC foundation and per-user access management were implemented.
- Global navigation search, table, modal, toggle, and responsive UI contracts were added.

### Provisioning and workforce

- Service-provisioning argument mismatch was corrected.
- Provisioning stage guards and resource-safety contracts were added.
- GPON selection and provisioning workflow protections were improved.
- Provisioning-to-work-order integration was implemented so physical installation can be confirmed by a technician.
- Work orders support manual technician assignment and location-aware automatic assignment.
- Tickets, workforce, technician management, attendance, and subscriber-portal integrations were reviewed and corrected.

### Subscriber, billing, and payments

- Subscriber plan dropdown and modal/backdrop issues were corrected.
- Subscriber ACS status/WAN data integration was reviewed and improved.
- Manual subscriber payments support Cash, Bank Transfer, GCash, and Maya with required proof upload and Billing approval.
- Manual payments stay pending until confirmation; suspended service is not restored before confirmed payment.
- Payment gateway and billing cross-module consistency was reviewed.

### Network modules

- BNG and CGNAT responsibilities were split into separate modules.
- BNG includes configurable Accel-PPP settings, draft/preview/apply workflow, interface/runtime views, and restart reconciliation support.
- BNG Preview uses unsaved form state and returns redacted configuration without applying it.
- VLAN creation integrates with Linux S-VLAN/C-VLAN interface creation using the configured BNG physical interface.
- CGNAT manages intended `iptables` POSTROUTING configuration and live rules, including removal/apply workflow.
- Routers module was added after BNG, with Core Router and FRR tabs.
- FRR uses `vtysh` on the BNG server; core router uses configurable Juniper management settings.
- OLT and ONT tables, VLAN presentation, discovery, inventory-derived ONT creation, and ACS operational views were modernized.
- NAP visual planner, map picker layout, nodes/links tables, and ODF/LCP/NAP parent/uplink display were revised.

### UI modernization

- A Vue 3/Tailwind `frontend-next` application now powers most modern module pages.
- Light/dark theming, modal backdrops, forms, tables, icons, spacing, responsive behavior, and toggle behavior were standardized progressively.
- Table action buttons generally use meaningful icons rather than word-only or ambiguous ellipsis actions.
- The NAP visual planner remains functional while using the newer visual system.
- The login page retains its particle animation.

### API tokens and transport policy

- API tokens are scoped and read-only by design for monitoring integrations.
- Per-token transport policy is implemented and enforced at authentication:
  - `HTTPS` — HTTPS only, default for newly created tokens
  - `HTTP` — HTTP only
  - `BOTH` — either transport
- Existing tokens default to `BOTH` for compatibility.
- Transport policy is stored in `api_tokens.transport_policy` and displayed during token creation/listing.
- Request transport enforcement uses the actual server HTTPS state and does not trust arbitrary forwarded-protocol headers.
- The token validator now works even when `mbstring` is unavailable.

Migration: `database/migrations/20260831_000001_api_token_transport_policy.sql`

## 8. HQ infrastructure monitoring

### Local collection and delivery

Scripts:

- `bin/collect-infrastructure-monitoring.php`
- `bin/deliver-infrastructure-monitoring.php`

Installed cron file:

- Source: `deploy/cron/nexusbox-infrastructure-monitoring`
- Installed: `/etc/cron.d/nexusbox-infrastructure-monitoring`

Expected installed content runs both scripts every minute as `nobody` and sends output to the system journal using tags:

- `nexusbox-infrastructure-monitoring`
- `nexusbox-infrastructure-monitoring-delivery`

Useful verification:

```bash
sudo journalctl \
  -t nexusbox-infrastructure-monitoring \
  -t nexusbox-infrastructure-monitoring-delivery \
  --since "10 minutes ago" \
  --no-pager
```

### Privacy boundary

The heartbeat is infrastructure-only. It must not contain subscriber identities, subscriber addresses, commercial/revenue data, invoices, payments, credentials, raw configurations, raw command output, ONT serials, or usernames.

It includes aggregate infrastructure data such as:

- Stable instance UUID and configured client/instance metadata
- Application version
- Collection time and freshness policy
- Hostname, kernel, uptime, load averages
- Memory and disk utilization
- Failed systemd unit count
- Service state, latency, version, and safe error codes
- BNG runtime aggregates such as Accel-PPP, FRR, IP forwarding, and conntrack utilization

### HQ schema compatibility

The local snapshot remains in the NexusBox internal schema. `MonitoringDeliveryService` builds an outbound compatibility envelope for HQ.

Important HQ-compatible fields include:

- `instance_uuid`
- `application_version`
- `resources.memory_percentage`
- `resources.disk_percentage`
- `resources.failed_systemd_unit_count`

Do not copy the nested `system.failed_systemd_units` object into `resources`; HQ expects scalar resource values and returns HTTP 422 otherwise.

### Current verified monitoring status

As of this handoff:

- Cron collection runs every minute.
- Delivery to HQ is enabled and working.
- Latest manually verified release heartbeat: snapshot `#1399`
- Snapshot `#1399` was `HEALTHY` and delivered successfully.
- HQ displayed application version `1.0.0`.
- HQ displayed current memory, disk, load, uptime, failed units, and service health.
- HQ service matrix showed current healthy states for platform/web, MariaDB, RADIUS, ACS, BNG, BNG runtime, CGNAT, FRR, OLT, and core router.
- HQ scheduler was retested as `HEALTHY`.
- The earlier eight-hour timestamp-display defect was fixed by the HQ application and retested for instance, service, and delivery timestamps. The user subsequently confirmed the final fleet-header timestamp was also fixed.
- Earlier HTTP 403 delivery failures were caused by HQ rejecting insecure transport. HQ transport authorization was changed to allow the approved private HTTP connection, after which delivery recovered automatically.
- Earlier HTTP 422 failures were caused by the nested failed-units object in `resources`; the outbound adapter now sends only the scalar count.

### Application versioning

Canonical version source:

```text
/var/www/nexusbox/VERSION
```

Current content:

```text
1.0.0
```

`APP_VERSION` may override this at deployment. When it is absent, Apache and cron use the `VERSION` file, preventing the former `development` value.

Follow semantic versioning:

- Patch: `1.0.1` for backward-compatible fixes
- Minor: `1.1.0` for backward-compatible features
- Major: `2.0.0` for breaking changes

Update `VERSION` before release deployment and verify the next HQ heartbeat.

## 9. Build procedures

There are two distinct builds. Do not confuse them.

### Modern Vue application

```bash
cd /var/www/nexusbox/frontend-next
npm run build
```

Output: `/var/www/nexusbox/public/build-next`

This is the build that affects modern module pages such as API Tokens.

### Legacy/root assets

```bash
cd /var/www/nexusbox
npm run build -- --configLoader runner
```

Output: `/var/www/nexusbox/public/build`

The `runner` loader avoids a known Vite `.vite-temp` permission issue. On this host, some source/build files have mixed service-account ownership. Do not broadly chmod the repository. Build using an account that can read the source, and ensure final public assets are readable by Apache.

## 10. Database migrations and backups

Authoritative baseline and ordered migrations are under `database/schema` and `database/migrations`.

Recent important migrations include:

- Audit expansion and command redaction
- Core integrity constraints
- Encrypted-secret column expansion
- API-token integration foundation
- Manual-payment proof and rejection support
- BNG/CGNAT split and hardening
- Router management
- RBAC foundation
- Monitoring identity, history, delivery readiness, and transport mode
- API-token transport policy

Apply migrations in filename order. Take a database backup before DDL. Never assume a migration is unapplied; check `information_schema` or the project’s migration state first. The API-token transport-policy migration is already applied on this server.

Operational data and external RADIUS test rows were deliberately cleared earlier with user authorization and backups. Subsequent QA/provisioning recreated some test state. Always query current counts instead of relying on historical counts in chat or this file.

## 11. Testing

### Backend/architecture contracts

Run the maintained suite:

```bash
cd /var/www/nexusbox
composer test
```

The suite covers module consistency, auditing, routes, provisioning safety, workforce integrations, ACS password-reset behavior, VLAN/BNG integration, API tokens, monitoring, frontend contracts, CGNAT, NAP, and security controls.

Additional architecture tests exist under `tests/Architecture`, including RBAC, global tables/toggles/navigation, manual payments, router management, BNG preview/monitoring, ONT consistency, and subscriber portal integration. Some tests are not listed in the Composer script; inspect and run relevant files directly when changing those areas.

### Frontend

```bash
cd /var/www/nexusbox/frontend-next
npm run build
npm test
```

The build runs `vue-tsc --noEmit` before Vite, so a successful build validates Vue/TypeScript compilation.

### Safe live testing rules

Default to authenticated GET-only/read-only testing. Login/session/audit side effects are acceptable only when authorized. Do not create, modify, delete, charge, disconnect, provision, apply network configuration, or change device state without explicit current authorization.

Payment providers are in test mode, but test-mode mutation still requires explicit authorization.

## 12. Current known limitations and follow-up considerations

- Transport to HQ remains private HTTP. Plan an HTTPS migration with certificate and hostname verification.
- Collection and delivery cron entries run at the same minute. This is safe but can leave the newest snapshot pending until the next delivery pass. Sequence them if lower heartbeat latency is desired.
- Redis is not configured on HQ; this was displayed as `NOT CONFIGURED`, not an ISP-instance failure.
- Some UI areas retain legacy implementations or module-specific complexity. Follow existing modern UI boundaries and avoid blanket CSS overrides.
- Network automation is high risk. Preview and validate rendered commands before apply; preserve redaction and auditing.
- Do not infer that all live device integrations are safe to mutate merely because the server is a test deployment.
- Automated RADIUS MariaDB blocked-host recovery artifacts are under `deploy/radius`. Install them locally on the RADIUS database server; do not grant the NexusBox web process MariaDB `RELOAD` or remote root privileges.
- The current repository may not have usable Git metadata in this workspace. Verify before relying on `git status` or rollback commands.

## 13. Recommended workflow for the next AI

1. Read this file and the relevant module’s DTO, validator, entity, repository, service, controller, routes, view, and frontend code.
2. Inspect the current runtime state read-only before proposing changes.
3. Check for an applicable architecture/security contract and add one for regressions.
4. Preserve protected runtime configuration and never expose secrets.
5. Use `apply_patch` for source edits.
6. Run PHP lint on every changed PHP file.
7. Run the relevant architecture/security tests and `composer test` for broad changes.
8. Build `frontend-next` for modern UI changes; build root assets only when legacy CSS/JS changed.
9. For migrations, back up first and make the migration idempotent or verify it is unapplied.
10. Report what was changed, what was verified, and what was not tested.

## 14. Key files

- `VERSION` — canonical application release
- `.env.runtime.php` — protected runtime configuration; never print it
- `bootstrap/container.php` — dependency injection bootstrap
- `framework/Router.php` — authentication, session/RBAC, bearer-token enforcement
- `app/Modules/Api/v1/Repositories/MonitoringRepository.php` — local infrastructure snapshot
- `app/Modules/Api/v1/Services/InfrastructureProbeService.php` — fixed, read-only infrastructure probes
- `app/Modules/Api/v1/Services/MonitoringDeliveryService.php` — HQ transport, signing, retry delivery, schema adapter
- `app/Modules/ApiTokens` — API-token creation, transport policy, monitoring operations UI/backend
- `docs/infrastructure-monitoring.md` — monitoring contract and operational notes
- `deploy/cron/nexusbox-infrastructure-monitoring` — scheduler definition
- `frontend-next/src/main.ts` — modern frontend mounting
- `frontend-next/src/modules` — modern module UIs
- `tests/Architecture` and `tests/Security` — regression contracts

## 15. Handoff status

The system is currently operational as an integrated ISP-in-a-Box test deployment. The HQ heartbeat pipeline is working, privacy-safe telemetry is visible at HQ, the HQ scheduler and timestamp defects were reported as fixed, and release version `1.0.0` is transmitted successfully. Continue cautiously, verify current state rather than relying solely on historical observations, and preserve the established security and audit boundaries.
