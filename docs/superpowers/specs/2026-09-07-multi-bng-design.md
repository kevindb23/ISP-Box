# Multi-BNG Connections Design

## Goal

Allow administrators to create, display, edit, trust, test, and delete multiple BNG connections while preserving encrypted credentials and preventing dependent configuration from targeting the wrong device.

## Design

`bng_settings` remains the connection store; each row is an independent BNG. The API accepts an optional `bng_id`, with the first enabled row retained as the backward-compatible default. The UI maintains an active BNG selection and sends that ID for runtime and configuration operations.

One application-wide `.env.secret-key` remains the encryption key for every BNG password. Host keys and passwords remain per record. Deletion is ID-scoped and is rejected when dependent desired-state records reference that BNG.

## Acceptance criteria

1. Two BNG records can be created and both appear in the table.
2. Editing or deleting one record does not modify another.
3. Host-key scan/trust and connection test operate on the selected record.
4. Runtime and dependent BNG configuration use the selected record.
5. Existing single-BNG requests remain functional.
6. Passwords remain encrypted with the application secret key.
7. API and PHP syntax checks pass.
