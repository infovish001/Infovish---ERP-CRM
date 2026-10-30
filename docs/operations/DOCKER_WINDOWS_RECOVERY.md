# Docker Desktop stale socket recovery

On 2026-10-30, Docker Desktop 4.85.0 failed before the Linux engine became available. Its host log reported inaccessible AF_UNIX endpoints, first `Docker/run/dockerInference`, then `docker-secrets-engine/engine.sock`, under the current user's LocalAppData directory. Both enumerated as zero-byte reparse points. Docker's own deletion attempt failed with Windows error text “The file cannot be accessed by the system.”

The failure matches reports in Docker's [desktop issue tracker](https://github.com/docker/desktop-feedback/issues/625). Those reports describe similar symptoms; they do not establish the cause on this machine.

## Repair performed

Inspected the recent `com.docker.backend.exe.log` and verified the exact runtime paths. Confirmed the secrets runtime directory contained only `engine.sock`. Stopped the crashed Docker Desktop/backend processes from the official Docker installation, renamed the runtime directories as backups, then restarted the installed Docker Desktop application. No factory reset, volume removal, WSL reset, credential change or settings edit was performed.

Preserved backups:

- `C:\Users\techv\AppData\Local\Docker\run-stale-20261030-085056`
- `C:\Users\techv\AppData\Local\Docker\run-stale-20261030-085155`
- `C:\Users\techv\AppData\Local\docker-secrets-engine-stale-20261030-085155`

The first restart exposed the second stale endpoint. The second restart restored Docker server 29.6.2. Docker Hub and ECR layer downloads then failed with connection resets; Google's Docker Hub cache supplied the same service-image versions, with full manifest digests verified against Docker Hub. Both service containers became healthy and real PostgreSQL/Redis/queue tests passed. Final image-build and integration results belong in the M01 report.

## If this recurs

Inspect the current host logs before attempting repair. Verify that affected directories contain runtime endpoints only. Preserve them by renaming with Docker stopped; never remove application data, WSL disks or Docker volumes as a general workaround. Host access is required because these paths are outside the repository. A future Docker or Windows update may change the behavior, so diagnose the current error rather than applying this procedure blindly.

Docker CLI status calls can hang while the backend is crashed. Use bounded diagnostic processes and terminate only the specific diagnostic child on timeout. Do not infer service availability from a running Desktop process alone.

## 2026-10-31 recurrence during M06

The system drive filled while PHPStan wrote its default temporary cache. The verified regenerable `C:\Users\techv\AppData\Local\Temp\phpstan` cache was removed and PHPStan now uses `backend/storage/framework/cache/phpstan` on the project drive. PostgreSQL/Redis timed out during the incident; the interrupted regression was not counted as passing evidence.

Docker status/restart calls stalled. After stopping only processes with verified paths under the official Docker installation and relaunching, the current rotated startup log reported the same inaccessible dockerInference socket. Both runtime directories were inspected and contained zero-byte socket reparse points only. With Docker stopped, they were preserved as `C:\Users\techv\AppData\Local\Docker\run-stale-20261031-0043` and `C:\Users\techv\AppData\Local\docker-secrets-engine-stale-20261031-0043`. The next launch restored server 29.6.2. Original project volumes/images/containers were verified before existing PostgreSQL/Redis containers were started; both became healthy. No factory reset, virtual disk modification or volume removal was performed. Follow current diagnostics if this recurs; this is not a generic cleanup recipe.
