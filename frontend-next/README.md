# NexusBox UI Next

This directory is an isolated frontend migration workspace. It does not replace
or mount into any current PHP module unless a view explicitly adds a
`data-nx-next-root` element and loads the generated `public/build-next` entry.

Protected boundary:

- no services, repositories, DTOs, validators, entities, routes, database code,
  jobs, integrations, or audit behavior are imported or modified here;
- the existing PHP application remains the source of authentication,
  authorization, validation, business rules, and API responses;
- module migrations must preserve their existing request and response contracts.

Commands:

```bash
npm install
npm run typecheck
npm run build
```
