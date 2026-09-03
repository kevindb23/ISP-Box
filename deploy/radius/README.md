# Automated MariaDB blocked-host recovery

Install these files on the RADIUS database server, not on the NexusBox web server. The service uses local Unix-socket root authentication, stores no password, and runs `flush-hosts` only when MariaDB's host cache reports a blocked entry.

```bash
sudo install -o root -g root -m 0755 nexusbox-radius-host-recovery /usr/local/sbin/nexusbox-radius-host-recovery
sudo install -o root -g root -m 0644 nexusbox-radius-host-recovery.service /etc/systemd/system/nexusbox-radius-host-recovery.service
sudo install -o root -g root -m 0644 nexusbox-radius-host-recovery.timer /etc/systemd/system/nexusbox-radius-host-recovery.timer
sudo systemctl daemon-reload
sudo systemctl enable --now nexusbox-radius-host-recovery.timer
```

The timer proactively flushes MariaDB's DNS/host cache every 15 seconds. The
operation is idempotent and prevents application hosts from accumulating enough
handshake errors to trigger MariaDB error 1129.

Verify:

```bash
sudo systemctl status nexusbox-radius-host-recovery.timer --no-pager
sudo systemctl start nexusbox-radius-host-recovery.service
sudo journalctl -t nexusbox-radius-host-recovery --since "10 minutes ago" --no-pager
```

The timer is recovery protection, not a substitute for investigating aborted connections, incorrect credentials, network instability, or an inappropriately low `max_connect_errors` setting.
