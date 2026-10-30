# Changelog

## Unreleased — 2026-10-30

- Added M04 CRM leads, company pipelines/stages, assigned opportunities/activities, immutable transition history and exact probability-weighted estimates.
- Added explicit customer conversion through Parties public contracts with transaction rollback, idempotency and concurrent conversion protection, plus schema-3 upgrades preserving master data.
- Added member/auditor CRM authorization, SQL-error privacy controls, CRM UI/API documentation and acceptance tests. M05 remains gated.

- Added M03 bundled module manifests/lifecycle/entitlements, additive tenant upgrades, company-scoped contacts and catalog variants/prices, immutable public DTO contracts, transactional numbering/idempotency and Redis outbox journal delivery.
- Added revision conflicts, master-data UI/API workflows, dependency/concurrency/upgrade/failure tests and operator runbooks.
- Corrected host-cookie initialization when Fortify resolves its session before tenant routing.

- Added M02 database-per-tenant provisioning, trusted domain routing, restricted runtime roles, identity/TOTP/session protection, organization and membership management, scoped audit history and versioned organization APIs.
- Added live multi-tenant PostgreSQL/Redis acceptance tests, CSRF/authorization/MFA regressions and operator setup documentation for M02.

- Preserved PRD v1.1 and SAD v1.0 as searchable repository transcriptions.
- Recorded M00 architecture review, module ownership, technology sources and ADR register.
- Verified the M01 engineering foundation, shell, probes, Docker app/worker build, live PostgreSQL/Redis/queue behavior, 11 backend tests and hosted CI.
- Recovered Docker Desktop from stale runtime sockets without resetting volumes; documented a verified-image registry fallback and configurable local app port.
- Separated Vitest from Laravel's development-server configuration and placed PHPUnit's cache in writable storage for non-root container tests.
