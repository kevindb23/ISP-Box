# NexusBox remediation report

## Executive summary

The approved remediation program has hardened credential transport and storage, financial writes, database integrity, authorization, API responses, audit coverage, and private uploads without replacing the modular PHP architecture. The explicit product exception remains: an authorized OLT details response may return the decrypted device password. OLT secrets remain encrypted at rest and are no longer exposed through process arguments or logs.

## Implemented controls

### Network automation and credentials

- Python automation receives JSON on standard input, never in `argv`.
- `NetworkCommandRunner` uses `proc_open` without a shell, bounded execution time, output limits, and redacted diagnostics.
- BNG password authentication uses a dedicated file descriptor; RADIUS CoA uses a temporary restricted secret file.
- OLT, ACS, BNG, RADIUS, PayMongo, and Xendit secrets use authenticated XChaCha20-Poly1305 storage (`enc:v1:`).
- Legacy provisioning command logs were redacted by migration `20260815_000002`.
- Runtime credentials were removed from tracked source and moved to ignored runtime files.

### Financial correctness

- Invoice, payment, and adjustment public numbers are derived from database identities rather than race-prone “latest plus one” queries.
- Payment and adjustment balance operations lock affected invoice rows.
- Payment voiding and adjustment voiding use row locks and transactions.
- PayMongo and Xendit checkout creation use per-invoice advisory locks and reuse eligible pending transactions.
- Gateway posting locks gateway transactions and invoices and treats an existing `payment_id` as already posted.
- Gateway references and other business identifiers have database uniqueness constraints.

### Database

- `database/schema/20260815_baseline.sql` is the authoritative 89-table baseline.
- Ordered migrations expand auditing, redact legacy secrets, add core foreign-key/unique constraints, and expand encrypted OLT credential storage.
- The live information-schema verifier confirms 29 named integrity constraints and the encrypted OLT password column capacity.
- A live read-only verifier confirms every populated managed secret uses the encrypted envelope.

### Authorization, APIs, and audit

- Central router authorization protects both browser and API routes and applies method-aware RBAC.
- Subscriber and technician ownership boundaries are enforced in services/repositories, not only in navigation.
- API tokens are random, stored only as SHA-256 hashes, shown once, expirable, revocable, rate limited, and tied to active users.
- Shared API responses expose `ok`, `success`, `status`, `message`, `data`, and `errors` consistently.
- All registered POST, PUT, PATCH, and DELETE routes are captured by the central audit trail; sensitive reads are also audited and nested secrets are redacted.

### Uploads and cleanup

- Work-order photos are stored outside the public document root and served through an ownership-checked download endpoint.
- The old public upload path is denied by Apache.
- Duplicate/orphan controllers, obsolete billing scaffolds, in-tree Radius backup code, source snapshots, and runtime-path backup files were removed.
- System Settings is now an independent, complete module instead of a Subscriber Plans submodule.

## Verification evidence

The regression suite includes:

- module-layer and dependency-injection contracts;
- controller construction against the live test database;
- route registration and role-authorization contracts;
- API response and audit coverage contracts;
- financial concurrency source invariants;
- network command and credential leakage tests;
- secret cipher tests and live encrypted-storage checks;
- private upload access checks;
- full PHP lint and Python compilation;
- authenticated GET-only browser checks for Superadmin, NOC, Support, Billing, Technician, and Subscriber roles.

No regression test invokes provisioning, disconnect, VLAN/OLT/BNG operations, RADIUS CoA, payment posting, or production-data changes.

## Explicit exception

The OLT details workflow intentionally retains plaintext password delivery to an authorized browser. This is a user-approved exception. Encryption at rest, backend RBAC, TLS deployment, session security, auditing, and restricted operator access are therefore mandatory compensating controls.

## Deployment actions still owned by the host administrator

- Serve only over HTTPS and redirect HTTP to HTTPS.
- Change all temporary QA credentials before deployment.
- Restrict `.env.runtime.php` and `.env.secret-key` to the application owner and web worker. The current test host requires mode `0604` because its web-worker ownership/ACL cannot be changed from this workspace.
- Remove the web-worker-owned generated file `app/Modules/ServiceProvisioning/Scripts/__pycache__/ztp_provision_ont.cpython-310.pyc` and ignore `__pycache__/` plus `*.py[cod]` at repository level. Workspace permissions currently prevent both operations.
- Back up the application encryption key in a secret manager before deployment.
- Run device integration tests only against non-production network equipment.
