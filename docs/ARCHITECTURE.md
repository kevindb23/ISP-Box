# NexusBox module architecture

Every live module follows this request path:

`Route → Controller → DTO → Validator → Service → Repository → Entity → Response`

- Routes contain only endpoint declarations.
- Controllers authorize through the global router, read input through `Framework\Request`, construct DTOs, invoke validators/services, and return the common response envelope.
- DTOs normalize transport input without querying databases or calling devices.
- Validators return field-keyed errors and have no side effects.
- Services own use-case rules, transaction boundaries, external operations, and domain audit events.
- Repositories are the only layer that issues SQL.
- Entities/read models prevent secrets and persistence-only fields from leaking into responses.
- `Framework\Response` is the API response boundary.

Controllers must receive dependencies from `Framework\Container`; direct construction of services, repositories, validators, or database connections is prohibited. All services participating in one transaction share the container-bound PDO handle.

## Audit policy

`RequestAuditTrail` is the mandatory safety net for every `POST`, `PUT`, `PATCH`, and `DELETE` request and for sensitive reads. It records actor, role, module, action, result, source, method, route, request ID, object reference, duration, and sanitized input. Passwords, tokens, credentials, cookies, and keys are always redacted.

Services additionally write domain events for meaningful state changes such as financial posting, account changes, ticket/work-order transitions, network configuration, provisioning, attendance, and gateway callbacks. Request events guarantee route coverage; domain events provide business context.

Static assets, health checks, and high-frequency dashboard polling are intentionally excluded. Failed, denied, and redirected audited requests remain visible with their result.

Run the contracts with:

```bash
php tests/Architecture/module_contract_test.php
php tests/Architecture/route_contract_test.php
php tests/Architecture/audit_contract_test.php
php tests/Architecture/controller_construction_test.php
```

The construction test opens database connections but performs no writes.
