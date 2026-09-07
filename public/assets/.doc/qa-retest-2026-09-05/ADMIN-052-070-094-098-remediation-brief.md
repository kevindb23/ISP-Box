# Admin QA remediation brief

## ADMIN-052 — Technician Management control names

**Problem:** The technician-management page exposed one or more enabled Vue controls without an accessible name.

**Solution:** Add explicit accessible labels to the technician search, availability filter, row availability selector, and work-order assignment selector. The shared `nx.js` label observer also re-checks controls mounted after Vue renders.

## ADMIN-070 — API Tokens control name

**Problem:** The HQ delivery switch was exposed as an unnamed `role="switch"` control.

**Solution:** Give the switch a stable accessible name describing its action: “Deliver infrastructure monitoring data to HQ.”

## ADMIN-094/096/097/098 — Subscriber Plan CRUD and RADIUS synchronization

**Problem:** Plan creation could insert a portal row and then fail with HTTP 500 when RADIUS synchronization failed. Update and delete then returned validation errors or left the UI inconsistent with the database. The configured RADIUS host is reachable, but the active credentials currently fail with MySQL error 1045 (`Access denied`).

**Solution:** Treat RADIUS synchronization as part of the plan persistence contract. The portal `plans.plan_name` column was also expanded from `VARCHAR(32)` to `VARCHAR(255)` through migration `20260905_000001_expand_plan_name.sql`. Create/update/delete must:

1. Validate the plan payload before mutation.
2. Synchronize the corresponding RADIUS authorization profile.
3. Commit the portal-side mutation only when synchronization succeeds.
4. On synchronization failure, return a controlled HTTP 503 with an actionable message and roll back only the in-flight portal mutation.
5. Return the created/updated identifier and re-read the record after success so the UI reload reflects the committed state.
6. Delete only the QA-created fixture during testing; preserve all pre-existing plans.

**Deployment prerequisite:** Correct and verify the active RADIUS database credentials in Administration → RADIUS. Until that succeeds, plan CRUD must remain rejected rather than silently creating plans that cannot authorize subscriber services.

## Verification

ADMIN-052 and ADMIN-070 pass in the latest authenticated Playwright admin regression. After the RADIUS credential correction and schema migration, a focused Playwright create → reload → update → fresh-read → delete flow passed: create HTTP 201, update HTTP 200 with persisted renamed plan, and delete HTTP 200. A subsequent full-suite rerun was inconclusive because the harness lost its authenticated context while locating an unrelated global notification control; it must be rerun separately for a clean full-suite result.
