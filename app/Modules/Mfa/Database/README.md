# MFA deployment

Apply `database/migrations/20260904_000001_mfa.sql` after taking a database backup.

Configure these runtime values before enabling an account:

- `MFA_ENCRYPTION_KEY`: base64 encoding of a randomly generated 32-byte key. Keep it outside the repository and do not rotate it without a secret re-encryption plan.
- `MFA_NODE_BINARY`: optional Node binary path; defaults to `node`.
- `MFA_SMTP_HOST`, `MFA_SMTP_PORT`, `MFA_SMTP_USER`, `MFA_SMTP_PASSWORD`, and `MFA_MAIL_FROM`: required for Email OTP.

The Node dependencies are declared in the repository root `package.json` and are used only by the QR and email helpers in `Scripts/`.
