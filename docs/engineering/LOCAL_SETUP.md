# Local setup and troubleshooting

Use PHP 8.5 with bcmath, curl, dom, fileinfo, mbstring, openssl, pdo_pgsql, tokenizer, XML and zip; Composer 2; Node 24 LTS; Docker Desktop Linux engine and Compose. Start Docker Desktop before service commands. Exact application dependencies live in lockfiles.

From the repository root in PowerShell 7:

```powershell
Copy-Item backend/.env.example backend/.env # first setup only
./scripts/setup-local.ps1
. ./scripts/php-local.ps1 # Windows host only; enables installed DLLs in this shell
Set-Location backend
composer install
npm ci
npm run build
Set-Location ..
docker compose up -d --wait postgres redis
Set-Location backend
php artisan migrate
php artisan foundation:queue-check
php artisan serve --host=127.0.0.1 --port=8000
```

Open http://localhost:8000. `/health` is process liveness and `/ready` checks real platform PostgreSQL/Redis connections. Dependency checks do not prove migrations or queue processing. No business account or tenant is seeded. `/` returns 404 outside local/testing. Keep development listeners on loopback. Redis is intentionally local and unauthenticated; never reuse this Compose topology or generated development credentials in production.

If port 8000 is already occupied, select a free Docker app port with `$env:APP_PORT = '8011'` before `docker compose up`, then open http://localhost:8011. The setting can also be retained as `APP_PORT=8011` in the ignored root `.env`. The application container continues listening on port 8000 internally.

For the complete Docker development path, prepare `.env` files with the setup script, then `docker compose up -d --build --wait`. Run `docker compose exec app php artisan migrate` and `docker compose exec app php artisan foundation:queue-check`. Run framework/frontend checks within the app or host as appropriate. Container PHP includes Linux worker signal support. This image contains developer dependencies and uses the Laravel development server; it is not a production deployment.

For container backend regression tests, explicitly select the dedicated database and test environment because Compose supplies application environment variables:

```powershell
docker compose exec -e APP_ENV=testing -e DB_DATABASE=infovish_test -e CACHE_STORE=array -e SESSION_DRIVER=array -e QUEUE_CONNECTION=sync -e RUN_INTEGRATION=1 app php vendor/bin/phpunit
```

Host verification (from `backend`):

```powershell
php vendor/bin/pint --test
php vendor/bin/phpstan analyse --memory-limit=512M
php artisan test --testsuite=Unit,Feature
php tools/check-boundaries.php
npm run typecheck
npm run lint
npm test
npm run build
node tools/check-api.mjs
$env:RUN_INTEGRATION = '1'
php artisan test --testsuite=Integration
Remove-Item Env:RUN_INTEGRATION
composer audit --locked
npm audit --audit-level=high
```

Integration tests require the separate `infovish_test` database. The initialization SQL creates it only on a fresh PostgreSQL volume. For an existing local volume, create that test database explicitly with `docker compose exec postgres createdb -U infovish_local infovish_test`; do not erase volumes to fix setup. Tests reject a different database name before migration/rollback checks. They do not implement tenant isolation tests (M02).

If Docker reports a missing pipe, start its Linux engine. A permission-denied pipe may be a sandbox restriction and requires the host's normal Docker access. Do not bypass engine security. If DLL loading fails, confirm the installed PHP extension directory and matching build; use Docker rather than downloading arbitrary DLLs. Network restrictions can block Composer/npm/image pulls; report these honestly. Do not pass `--ignore-platform-reqs`, `--force` or `--legacy-peer-deps` to disguise incompatibility.

If Docker's host log reports inaccessible `dockerInference` or `engine.sock` runtime endpoints, see [the observed Windows recovery procedure](../operations/DOCKER_WINDOWS_RECOVERY.md). Do not reset volumes or WSL distributions to repair a stale runtime socket.

If Docker Hub's layer CDN repeatedly closes connections, the same official image versions can be fetched through Google's public Docker Hub cache without changing the global Docker daemon configuration:

```powershell
$env:POSTGRES_IMAGE = 'mirror.gcr.io/library/postgres:17.11-alpine'
$env:REDIS_IMAGE = 'mirror.gcr.io/library/redis:8.10-alpine'
docker compose up -d --wait postgres redis
docker compose build --build-arg IMAGE_REGISTRY=mirror.gcr.io/library
docker compose up -d --wait
```

Cache availability is not guaranteed. Verify mirrored image identities against the canonical registry and retain the same versions. Default Compose/build configuration continues to use Docker Hub. Do not disable TLS verification to work around a download failure.

PostgreSQL is mapped to 54329 and Redis to 63799 to reduce conflicts. Stop containers with `docker compose stop`; preserve volumes. Changing the generated password on an existing PostgreSQL volume does not rotate its stored role password. Hosted CI requires a configured GitHub remote and actual run; local checks cannot certify GitHub Actions success.
