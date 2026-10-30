# Changelog

## Unreleased — 2026-10-30

- Preserved PRD v1.1 and SAD v1.0 as searchable repository transcriptions.
- Recorded M00 architecture review, module ownership, technology sources and ADR register.
- Verified the M01 engineering foundation, shell, probes, Docker app/worker build, live PostgreSQL/Redis/queue behavior, 11 backend tests and hosted CI.
- Recovered Docker Desktop from stale runtime sockets without resetting volumes; documented a verified-image registry fallback and configurable local app port.
- Separated Vitest from Laravel's development-server configuration and placed PHPUnit's cache in writable storage for non-root container tests.
