# Plans migration contract

The first module migration must preserve the existing backend interface.

## Existing endpoints

- `GET /api/v1/subscriber-plans`
- `POST /api/v1/subscriber-plans/store`
- `POST /api/v1/subscriber-plans/update/{id}`
- `POST /api/v1/subscriber-plans/delete`

## Protected behavior

- PHP authentication, RBAC, CSRF validation, DTO construction, validation,
  service logic, repository writes, and audit logging remain authoritative.
- The Vue screen may format values and manage presentation state only.
- Mutation integration is deferred until request payloads, error envelopes,
  CSRF handling, and audit outcomes have dedicated regression coverage.

## Controlled live milestone

The Plans view is the only PHP module allowed to load `public/build-next`.
`?legacy_ui=1` immediately returns the prior server-rendered interface. All
mutations use the existing endpoint names, same-origin PHP session, and CSRF
header before refreshing from the authoritative backend response.
