# Owner deployment guide

Status: planning and existing Docker foundation; CI/CD implementation is scheduled as Phase 8, immediately after Phase 7. There are no GitHub workflow files yet. The owner performs infrastructure setup, secret configuration, activation and deployment. This guide does not claim a live environment or completed restore drill.

## What is already available

The production Dockerfile builds application/PHP and web/Caddy images. `deploy/compose.yaml` defines the application, queue worker, scheduler, web server and PostgreSQL with persistent database/attachment volumes. `deploy/README.md` documents runtime database grants, paired backup and isolated restore. Existing Compose currently builds local images; registry-based release deployment will be added in Phase 8.

## Planned delivery flow

Push or pull request → isolated checks → trusted release-branch image publication → opt-in deployment → readiness verification. A local commit alone does not trigger GitHub Actions. Feature branches and pull requests never receive deployment secrets. Default proposal: successful main updates deploy automatically after the owner activates the configured environment. Build application and web images from one commit, retain their digests and deploy that exact pair.

Checks run inside Docker with temporary PostgreSQL, never the agency database: tests, formatting, frontend compilation and production image validation. Only successful checks permit publication/deployment. Deployments are serialized; never cancel a running migration to start a newer release. Pin third-party actions to reviewed commit SHAs and give jobs only required permissions.

## Owner inputs before activation

- Confirm release branch, repository visibility/GitHub plan, staging and production layout, GHCR image visibility and notification destination.
- Provision VM(s), confirm OS/architecture, install Docker Engine and Compose, allocate persistent storage, configure domain/DNS and HTTPS.
- Provide a private deployment connection: VPN connectivity or a dedicated deployment runner inside the infrastructure. Keep SSH restricted; do not run untrusted pull-request jobs on a deployment runner or production host.
- Create a dedicated deployment identity and pin SSH host identity if SSH is selected. Docker access is privileged; restrict the identity to its intended deployment responsibility.
- Configure registry pull access for private images, GitHub environment secrets, and branch/environment protections as supported by the repository plan. Do not send secrets in chat or commit them.
- Choose a brief maintenance window, encrypted off-host backup destination, retention and failure alerts.

Exact workflow secret names and copy/paste activation commands will be documented with the Phase 8 implementation; they are not configured yet.

## Initial host setup

Follow `deploy/README.md` for the current manual Docker process. Copy the example application/database environment files to their ignored counterparts; use independent staging/production values and protect file permissions. Keep application credentials separate from database-owner migration credentials. Generate and preserve APP_KEY securely; changing it can invalidate encrypted data. Do not seed demo records in production.

Review sample agency/contract content, provision the first Manager, apply migrations using the owner role, then apply runtime-role grants. Maintain distinct staging/production volumes and keys. Never share a test database with production. Configure daily paired backups, off-host copying, disk/queue/HTTP/backup monitoring and the private readiness probe described in the operations notes.

## Before enabling automated releases

1. Complete the Phase 8 foundation and run its disposable-environment rehearsal.
2. Configure CI and required branch checks; verify a deliberately failing check prevents deployment.
3. Configure registry access and the private deployment connection. Add environment secrets through GitHub and host secrets through protected files.
4. Deploy to staging; verify migrations, runtime grants, login, uploads/downloads, worker/scheduler, FR/AR/EN and a synthetic rental/payment/return workflow.
5. Restore a paired database/attachment backup into an isolated target and measure RPO ≤24h / RTO ≤4h. Never test restoration over the live database.
6. Reconcile actual import counts, outstanding debt and held deposits; complete staff acceptance and replace sample legal content.
7. Enable the production deployment environment only after the above checks and owner approval of initial cutover. Subsequent successful release-branch pushes can then deploy automatically.

## Per-release procedure to automate in Phase 8

Preflight configuration/storage/connectivity → pull exact app/web image digests → acquire deployment lock → pause application writes and stop workers/scheduler → paired backup → migrate with owner credentials and review/apply required runtime grants → start new containers and rebuild caches → verify readiness and background processing → restore traffic and record release identity. Failures must remain visible, with the maintenance state and recovery instructions reported accurately. A plain `/up` response alone is insufficient to prove database and attachment readiness.

## Recovery

Keep the previous image pair and release metadata. Revert application images only when the database schema remains compatible. Prefer additive migrations so old/new code can coexist during recovery. Do not automatically run migrate:rollback or restore an old backup over newly recorded rentals/payments. When data recovery is necessary, use the isolated paired-restore procedure, reconcile post-backup transactions and let the owner approve traffic cutover. A workflow failure is not evidence that rollback succeeded.

## Handoff boundary

Implementation provides workflow/script/configuration files, isolated verification and a finalized runbook in Phase 8. The owner supplies infrastructure and accounts, stores secrets, enables workflows/environments, runs the first real deployment and owns ongoing operations. Pending Phase 5 launch gates remain open until actually performed.
