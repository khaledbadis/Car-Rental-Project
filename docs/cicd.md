# Car Rental Project — CI/CD & Dokploy Migration Requirements

## 1. Objective

Adapt the existing Car Rental Project to the standardized development and deployment workflow:

**Local Development → GitHub → GitHub Actions (CI) → GHCR → Dokploy (CD) → Production VPS**

The goal is to automate testing, image building, publishing, and deployment while maintaining portability between shared and dedicated VPS environments.

**Important:** Preserve the existing application architecture, functionality, and Docker development environment. Modify only what is necessary to implement the new deployment workflow.

## 2. Current State

Repository: https://github.com/khaledbadis/Car-Rental-Project

Branch: `master`

### Already implemented
- Docker-based development environment (`compose.yaml`).
- Production multi-stage Dockerfile (`docker/Dockerfile.production`).
- Separate development and production configurations.
- PostgreSQL 17.
- Dedicated application, web, worker, and scheduler services.
- Local automated checks (`scripts/check`).
- Production environment templates.
- Database backup script and recovery documentation.
- Separation of database owner and application runtime credentials.

### Missing or requiring modification
- GitHub Actions CI pipeline.
- Automated GHCR image publishing.
- Dokploy deployment integration.
- Production Compose configuration using prebuilt images.
- Reverse-proxy configuration compatible with Dokploy.
- Automated release verification and rollback procedures.
- Operational monitoring and backup verification.

## 3. Required Changes

### Update Project Specifications

Update `project-spec.md` to document:

- Standardized CI/CD architecture.
- GitHub Actions responsibilities.
- GHCR image versioning.
- Dokploy deployment responsibilities.
- Supported deployment targets (shared VPS or dedicated VPS).
- Environment and secret management.
- Release, rollback, and disaster-recovery procedures.

Preserve existing project requirements and architectural decisions.

### Implement GitHub Actions CI

Create `.github/workflows/ci.yml`.

The workflow must:

1. Trigger tests for pull requests and relevant pushes.
2. Set up the PHP/Laravel and PostgreSQL test environment.
3. Run Laravel automated tests.
4. Run Laravel Pint checks.
5. Validate frontend asset compilation.
6. Build production Docker images.
7. Fail the pipeline if any required check fails.

Reuse the validation logic from `scripts/check` where practical.

Ensure automated tests cannot access development or production databases.

### Build and Publish Production Images

Extend GitHub Actions to:

1. Build the production `app` and `web` image targets.
2. Publish both images to GitHub Container Registry (GHCR).
3. Tag images with their Git commit SHA.
4. Optionally publish human-readable release tags.
5. Publish images only after all CI checks pass.
6. Use appropriate GitHub Actions permissions and registry authentication.

The worker and scheduler must reuse the same application image.

**Requirement:** Build images once in CI and deploy the exact same image versions to production.

### Adapt Production Docker Compose

Modify the production Compose configuration to:

- Reference GHCR images instead of building from source.
- Support configurable release image versions.
- Preserve existing application, worker, scheduler, web, and database services.
- Preserve database and attachment persistence.
- Maintain appropriate service health checks and restart policies.
- Keep PostgreSQL and PHP-FPM inaccessible from the public internet.
- Maintain database credential separation.

Keep the local development Compose configuration independent.

### Integrate Dokploy

Prepare the project for image-based deployment through Dokploy.

Requirements:

- Dokploy pulls prebuilt images from GHCR.
- Deployment targets can be either the main VPS or another VPS managed remotely by Dokploy.
- Configure private GHCR authentication if required.
- Use Dokploy-managed domains and HTTPS.
- Avoid exposing unnecessary container ports.
- Support automated deployment after successful CI, with manual approval available for production releases.
- Ensure failed deployments can be detected and recovered from.

**Reverse proxy:** Dokploy uses Traefik for public routing and TLS termination. Keep Caddy as the internal application web server if appropriate, but remove conflicting public HTTPS and port bindings.

### Environment and Secrets

Maintain strict separation between development, staging, and production.

Requirements:

- No production secrets committed to Git.
- Store runtime secrets securely in Dokploy or another appropriate secret-management system.
- Keep `APP_DEBUG=false` in production.
- Preserve `APP_KEY` between deployments.
- Use separate PostgreSQL credentials and databases for each environment.
- Maintain separate database-owner and restricted runtime roles.
- Ensure staging and production use isolated data and storage.
- Document all required environment variables using safe `.env.example` files.

### Production Release and Operations

Implement or document:

- A safe database migration process using the appropriate privileged database role.
- Deployment health checks.
- Application, worker, scheduler, and database verification.
- Monitoring and alerting.
- Database and attachment backups.
- Off-server backup storage.
- Periodic restore testing.
- Release history and rollback procedures.

Database migrations must not run concurrently from multiple application containers.

**Important:** Rolling back a Docker image does not automatically roll back database schema changes. Prefer backward-compatible migrations and explicitly document recovery procedures for incompatible schema changes.

## 4. Expected Final Workflow

1. Developer writes and tests code locally using Docker Compose.
2. Developer commits and pushes code to GitHub.
3. GitHub Actions executes automated tests and checks.
4. Successful CI builds production Docker images.
5. Images are published to GHCR with immutable version identifiers.
6. Dokploy pulls the approved release images.
7. Dokploy deploys the containers to the selected VPS.
8. Application migrations and health verification are performed safely.
9. Production runs with persistent data, monitoring, backups, and rollback capability.

## 5. Acceptance Criteria

Implementation is considered complete when:

- [ ] Local development continues working without changes to its normal workflow.
- [ ] GitHub Actions automatically validates pull requests.
- [ ] Production images are built automatically after successful validation.
- [ ] Both production images are published to GHCR.
- [ ] Images are versioned and reproducibly deployable.
- [ ] Production Compose no longer requires source-code builds.
- [ ] Dokploy can deploy the application using GHCR images.
- [ ] Application routing and HTTPS work through Dokploy/Traefik.
- [ ] PostgreSQL and uploaded files persist across deployments.
- [ ] Secrets remain outside the repository and container images.
- [ ] Deployment and migration failures have a documented recovery procedure.
- [ ] Backups and an isolated restoration procedure are verified.
- [ ] Deployment documentation is updated.
- [ ] `project-spec.md` reflects the final architecture and implementation status.

## 6. Instructions for the AI Agent

1. Inspect the entire existing repository before modifying anything.
2. Update `project-spec.md` with the new deployment architecture and implementation phases.
3. Reuse existing Docker configurations and scripts wherever possible.
4. Implement the required changes incrementally.
5. Do not modify application business logic unless strictly necessary.
6. Do not remove existing security controls, database role separation, or backup procedures.
7. Prefer simple, maintainable solutions over unnecessary infrastructure complexity.
8. Do not assume access to GitHub repository secrets, GHCR credentials, a Dokploy instance, or a production VPS.
9. Clearly distinguish implemented and locally verified features from infrastructure tasks requiring manual configuration.
10. Provide documentation for GitHub repository settings, GHCR access, Dokploy configuration, environment variables, and first deployment.

**Final deliverable:** A production-ready, portable, Docker-based deployment configuration following the agreed GitHub Actions → GHCR → Dokploy architecture, with any remaining external infrastructure setup explicitly documented.