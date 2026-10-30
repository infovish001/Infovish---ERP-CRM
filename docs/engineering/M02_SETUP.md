# M02 local operator setup

Prepare PHP/Composer/Node and Docker as described in [local setup](LOCAL_SETUP.md). Commands below run from the repository root in PowerShell 7. Keep the root `.env` operator password and `backend/.env` application key private; tenant credentials are encrypted with that key. Preserve both database backups and the key together under appropriate access controls.

```powershell
./scripts/setup-local.ps1 # first installation only
docker compose up -d --wait postgres redis
./scripts/platform-local.ps1 Migrate
./scripts/platform-local.ps1 RuntimeRole
./scripts/platform-local.ps1 User owner@example.com 'Workspace Owner'
./scripts/platform-local.ps1 Tenant example 'Example Organization' example.localhost owner@example.com
docker compose up -d --build --wait
```

The User command prompts for a hidden password (minimum 14 characters with mixed case, numbers and symbols). No seeded/default identity exists. RuntimeRole generates a restricted platform application credential in ignored `backend/.env`; rerunning it rotates that credential, so recreate app/worker containers afterward. Run migrations before configuring/reconfiguring runtime privileges. Future migrations require a grant review, especially new protected metadata or audit tables.

The helper reads the local operator password from the ignored root `.env`, supplies `PROVISIONING_DB_*` only during the CLI process, then restores the caller environment. Never put provisioning variables in `backend/.env`, Compose web/worker configuration or a browser request. The runtime role cannot create databases/roles, change the tenant directory or update/delete audit records. Each tenant receives its own database and login role; runtime tenant credentials cannot create schema or connect to the platform or another tenant database.

Set `APP_PORT=8011` in root `.env` if the default port is occupied. Open `http://example.localhost:8011` using the selected port. `.localhost` resolves to loopback without editing DNS. Exact hostnames are registered independently of ports. Other hostnames require separate DNS, TLS and routing configuration; registration alone publishes nothing.

On first sign-in, an owner/administrator must confirm their password, enroll an authenticator using the displayed setup key, confirm its code and privately retain recovery codes. Change the generated password in Account security. These actions are for the account owner. Organization setup then allows real companies (explicit currency/timezone), branches and departments. No sample business records are inserted.

```powershell
./scripts/platform-local.ps1 Domain example another.localhost
./scripts/platform-local.ps1 State example suspended
./scripts/platform-local.ps1 State example active
./scripts/platform-local.ps1 Test
```

Provisioning retries preserve the same tenant identity and partial resources. Do not delete databases/roles to recover a failed provision. A domain cannot be reassigned to another tenant through this command. Suspension preserves data and denies subsequent requests/jobs.

`Test` migrates only `infovish_test`, generates a restricted test application role, and runs the complete backend suite with separate operator credentials for fixture provisioning. Tests reject other database names, create uniquely named disposable tenant databases/roles and clean up those exact resources. Use a dedicated local/CI cluster; PostgreSQL roles are cluster-wide. Do not run tests concurrently against the same test runtime role or shared test database. A fresh volume creates `infovish_test`; for an existing volume, create it explicitly as documented in LOCAL_SETUP.md. Unit/feature tests alone do not verify tenancy.

Container integration runs need the same dedicated database, restricted test role and provisioning credentials injected only into the ephemeral test process. The production-like web/worker environment must remain without provisioning credentials. Hosted CI demonstrates the complete sequence. Do not run platform migrations through the restricted web role.

This installation uses the development server, local HTTP and loopback-only ports. Public production deployment, TLS/secure cookies, trusted edge configuration, managed secrets, email delivery/recovery, backup restoration drills and security assessment remain release gates.
