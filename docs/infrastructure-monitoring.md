# Infrastructure monitoring

NexusBox exposes privacy-safe infrastructure telemetry at:

- `GET /api/v1/monitoring/infrastructure/snapshot`
- `GET /api/v1/monitoring/infrastructure/history?hours=24`

Use a bearer token with only `infrastructure.monitoring.read`. These responses exclude subscriber, commercial, credential, raw configuration, and raw log data.

## Scheduled collection

Install the supplied `/etc/cron.d` file. It runs collection and delivery once per minute as the unprivileged `nobody` account and sends results to the system journal:

```bash
sudo install -o root -g root -m 0644 deploy/cron/nexusbox-infrastructure-monitoring /etc/cron.d/nexusbox-infrastructure-monitoring
sudo systemctl restart cron
```

Snapshots are retained for 30 days by default. `MONITORING_RETENTION_DAYS`, between 1 and 365, overrides retention. Status transitions are retained separately so HQ can distinguish an active incident from a recovered incident.

The application release is read from the root `VERSION` file. A deployment may override it with the `APP_VERSION` environment variable. Keep the value in semantic-version form, such as `1.0.0`.

Verify scheduled runs with:

```bash
sudo journalctl -t nexusbox-infrastructure-monitoring -t nexusbox-infrastructure-monitoring-delivery --since "2 minutes ago" --no-pager
```

## HQ delivery configuration

Leave `system_config.monitoring_hq_enabled` set to `0` until the HQ HTTPS receiver is ready. Configure its base URL in `system_config.monitoring_hq_url`. Store the shared token outside the database by adding this section to the protected `.env.runtime.php` array:

```php
'monitoring' => [
    'hq_url' => 'https://hq.example.com', // optional override of system_config
    'hq_token' => 'replace-with-at-least-32-random-bytes',
],
```

`HQ_MONITORING_URL` and `HQ_MONITORING_TOKEN` environment variables take precedence. The runtime file must remain protected and readable by the scheduled application account. Enable delivery only after the receiver is available. Enabling it queues newly collected snapshots; it does not export older history.

Failed deliveries use exponential backoff from 30 seconds up to 15 minutes and become `FAILED` after 20 attempts. A 2xx response marks the item delivered.

## HQ receiver contract

The instance sends `POST /api/v1/instances/heartbeat` over verified HTTPS with the infrastructure snapshot as its JSON body. The receiver must authenticate the bearer token, enforce a small timestamp tolerance (recommended: 300 seconds), validate the body hash and HMAC signature, and deduplicate by delivery ID.

| Header | Meaning |
|---|---|
| `Authorization` | `Bearer <instance shared token>` |
| `X-NexusBox-Schema` | Payload contract version, currently `1.0` |
| `X-NexusBox-Instance` | Stable instance UUID from the payload |
| `X-NexusBox-Timestamp` | Unix timestamp when the request was signed |
| `X-NexusBox-Delivery` | Idempotency key: `<instance_id>:<snapshot_id>` |
| `X-NexusBox-Payload-SHA256` | Lowercase SHA-256 hash of the exact body |
| `X-NexusBox-Signature` | `v1=` followed by lowercase HMAC-SHA256 |

Signature input is:

```text
<timestamp>.<delivery_id>.<payload_sha256>
```

The HMAC key is the instance shared token. Compare signatures in constant time. Return 2xx only after durable acceptance; a duplicate delivery ID should return 2xx without inserting another heartbeat. Use 401/403 for authentication failure, 409 for a delivery-ID payload conflict, 422 for invalid schema, and 429/5xx for retryable conditions.

## Privacy boundary

Only aggregate host, daemon, endpoint, reachability, utilization, status, latency, version, configuration-hash, and incident timing fields are collected. Do not add usernames, subscriber IP addresses, ONT serial numbers, payment information, credentials, raw command output, or raw configuration to the payload.
