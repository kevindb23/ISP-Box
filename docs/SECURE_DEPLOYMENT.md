# Secure deployment requirements

NexusBox requires PHP 8.1+, PDO MySQL, cURL, Fileinfo, JSON, and Sodium. The web document root must be `public/`, never the repository root.

## Runtime credentials

Production should provide `PORTAL_DB_HOST`, `PORTAL_DB_USER`, `PORTAL_DB_PASS`, `PORTAL_DB_NAME`, `COA_HOST`, `COA_PORT`, `COA_SECRET`, and optionally `RADCLIENT_PATH` through the service environment. The local test server currently uses `.env.runtime.php`, which is outside the document root and ignored by the repository. Restrict it to the application owner and web worker only.

`APP_SECRET_KEY` must be a base64-encoded 32-byte value. The test server uses `.env.secret-key`. Back this file up in the deployment secret manager: losing it makes encrypted OLT, ACS, BNG, RADIUS, PayMongo, and Xendit values unrecoverable. Never rotate it by replacing the file directly; decrypt and re-encrypt stored values as an explicit rotation operation.

## Database

The authoritative starting schema is `database/schema/20260815_baseline.sql`. Apply migrations in filename order and take a database backup before DDL. The test-server migrations through `20260815_000004` have been applied.

## Web server

- Serve HTTPS and redirect HTTP to HTTPS. HSTS is emitted only for HTTPS requests.
- Preserve `Authorization` and `X-Forwarded-Proto` headers.
- Deny direct access to dotfiles and `public/uploads/work-orders`; `.htaccess` supplies the application fallback and the attachment denial for Apache.
- Ensure the web worker can read the runtime/key files without making them readable by unrelated operating-system users.
- Keep `storage/uploads/work-orders` outside the document root and writable only by the application.

## Verification

Run `composer test`, the controller construction test, PHP lint, Python compilation, and authenticated role checks before rollout. Network automation tests must use non-production devices; routine regression checks must not invoke provisioning, disconnect, OLT, VLAN, BNG, RADIUS CoA, or payment actions.
