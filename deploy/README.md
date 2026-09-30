# Deployment preparation — not provisioned

Use separate staging and production VMs/domains. Suggested initial VM: 2 vCPU, 4 GB RAM, 80 GB SSD, capacity alerts and separate encrypted backup storage. Measure usage before resizing. Restrict SSH to the owner network; expose only 80/443 publicly. PostgreSQL and FPM have no published ports.

1. Copy .env.example to .env and db.env.example to db.env (both ignored). Keep database-owner secrets only in db.env; application containers receive only runtime credentials. Set domain, URL, unique owner/runtime passwords and APP_KEY. Keep APP_KEY in a protected recovery secret store.
2. Build with docker compose build. Use a pinned release tag and retain the previous image. Start db.
3. Run migrations as the owner role in a one-off app container, passing credentials through a protected environment file. Do not use the runtime role for migrations.
4. Run runtime-role.sql as owner using a protected runtime_password psql variable. Review grants after migrations. Runtime role must not own objects or have superuser/DDL rights.
5. Start all services. Caddy terminates HTTPS and renews certificates. Verify /up, login, uploads, worker and scheduler. Use separate volume sets and keys for staging/production.
6. Provision the first Manager with app:create-manager. Never seed demos in production. Replace config/contract.php sample content only after agency approval.
7. Rehearse migration/reconciliation and a measured restore before staff acceptance and cutover.

## Backups and monitoring

Schedule backup.sh daily on the host with BACKUP_DIR set to separate protected storage. It takes the app down and stops app/worker/scheduler to pair PostgreSQL and attachment snapshots, then resumes them on exit. Run in an agreed maintenance window. Check SHA256SUMS and encrypt/copy backups off-host. Alert when no successful backup exists within 24 hours. Initial retention: 30 daily copies, subject to owner policy.

Monitor HTTPS /up externally, container restarts, disk growth, PostgreSQL health, PHP stderr, failed queue jobs and backup freshness. /up alone does not prove database/filesystem health; use an authenticated private operational probe. The owner’s alert destination remains to be configured.

## Restore drill — isolated target only

1. Record start time and verify SHA256SUMS.
2. Prepare an empty isolated VM/Compose project with the same application image and PostgreSQL 17; no public DNS or access to live data.
3. Stop target app/worker/scheduler. Restore database.dump with pg_restore --exit-on-error into an empty target database as owner; recreate runtime role grants.
4. Restore attachments.tar.gz into the empty attachments volume with UID/GID 33 ownership. Restore APP_KEY from the secret store.
5. Start the target. Verify users, rental/ledger totals, receipt numbers, commitments, attachment hashes and authorized downloads. Complete a synthetic end-to-end workflow. Record elapsed time.
6. Launch requires RPO ≤24h and measured RTO ≤4h for both database and files. Prepared scripts alone do not pass this gate.

## Cutover and recovery

Agree a freeze time, make legacy spreadsheets read-only, export final data, reconcile exact remaining deposits and opening debt, enter intervening paper transactions with original timestamps, then authorize launch.

Keep a compatible previous image. Do not destructively roll back migrations over posted financial records. Forward-fix or restore a paired backup into a separate recovery target, reconcile post-backup transactions, then switch traffic. Production restore/cutover requires owner approval of the specific target and recovery point.
