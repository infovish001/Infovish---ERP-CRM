# M03 implementation and verification report

Status: IN PROGRESS pending hosted CI. Host/container and authenticated HTTP/browser acceptance passed. Authorized by the user's continuation after M02 handoff on 2026-10-30. Stop before M04.

## Delivered baseline and decisions

The [M03 specification](M03_SPEC.md) refines PRD MOD/ORG, contacts/catalog dependencies, NFR-001/002/003/006/007/009/013 and DEP-001 through DEP-005/007; SAD sections 3â€“6/9. It does not claim entire grouped requirement ranges or commercial readiness. [ADR-020](../architecture/decisions/ADR-020.md) extends ADR-006 with a concrete bundled-module and transactional delivery baseline.

Implemented two explicit allowlisted modules, Parties and Catalog, with version/framework/dependency/public-contract/migration manifests. Platform operator entitlements, installed tenant schema version, workspace activation and actor/company permissions independently gate access. Activation runs no DDL or uploaded code; deactivation preserves data. Operator upgrades lock, suspend, verify exact database identity before DDL, apply additive migrations/grants and record readiness only on success. Failed upgrades preserve data and remain suspended for review/retry. Existing schema-1 identity/organization remains supported until upgrade.

Contacts support customer/supplier flags, normalized company-unique optional email, phone, permanent company-local numbers, search/pagination, revisions and deactivation. Products/services support permanent numbers, immutable type/unit, initial and additional variants, immutable company-local SKUs, string attributes and optional base-currency NUMERIC(20,6) list prices returned as strings. No floating-point arithmetic, tax, discount, stock or currency-conversion behavior is introduced. Public Contacts/Products contracts return immutable DTOs and enforce active module/record/company authorization; no persistence model crosses a module boundary.

Shared tenant transactions cover locked numbering, actor/company/operation-scoped idempotency with request-hash conflict rejection, optimistic revision checks, allowlisted audits and ID-only versioned outbox events. A bounded operator dispatcher and Redis worker provide at-least-once delivery into a deduplicated internal event journal. This is not a claim of future business subscribers or external notifications. Processing revalidates original author, dispatcher, company and module; savepoints preserve durable retries after database consumer failure. Backoff, dead-letter state and audited privileged replay are inspectable.

The React interface follows the observed Figma navy sidebar, compact header, pale workspace, rounded panels and professional table/form styling. Inventory reference frames were inspected again; they contain illustrative data and later inventory workflows, so no stock metrics or transfers were copied. Contacts/catalog-specific exact frames were not provided; the screens implement the documented M03 workflows using those observed conventions.

## Verification ledger

| Check | Status and evidence |
|---|---|
| M02 regression during initial M03 development | PASS: 30 tests / 193 assertions |
| Targeted M03 integration | PASS: 15 tests / 116 assertions before final cookie regression addition |
| Concurrent matching retry and distinct numbering repeat | PASS: five targeted repeats plus full host/container runs after an earlier intermittent returned-output mismatch; no persistence defect reproduced |
| PHP formatting and PHPStan level 6 | PASS, including final session fix |
| TypeScript / ESLint / Vitest / production build | PASS: 8 interaction tests across 3 files |
| OpenAPI / module boundaries | PASS; two actual bundled modules and versioned APIs |
| Composer validation/audit / npm audit | PASS; no reported advisories |
| Final host backend suite | PASS: 48 tests / 321 assertions on PHP 8.5.10 |
| Final Docker build and isolated complete suite | PASS: 48 tests / 321 assertions on PHP 8.5.12; app/worker persistent environments have no provisioning variables. Final CSS build/start verified separately; no backend logic changed afterward |
| Real HTTP cookie/MFA and authenticated browser workflows | PASS: real HTTP login/MFA/workspace=200 after the fix; browser recovery sign-in, contact/product/variant creation, exact prices, mobile navigation/dark theme and three actual worker-delivered events |
| Local secret scan | PASS: Gitleaks 8.30.1 scanned all Git-includable source with no leaks |
| Hosted CI and repository-history secret scan | Pending publication |

The real browser/HTTP test exposed a pre-existing session initialization order bug: Fortify can resolve its guard/store before tenant routing changes the configured cookie name, while ordinary authenticated routes resolve it afterward. The host resolver now also sets the already-created session store name and restores it in finally. A regression forces early store initialization and checks the issued host cookie. Array-backed feature authentication alone had not revealed this real HTTP cookie mismatch.

Tests cover real PostgreSQL tenant roles/databases, included/denied entitlements, read-only activation/DDL metadata, activation dependencies, module revocation, company grants, duplicate IDs across tenants, create retries/conflicts, stale revisions, decimal rejection/precision, variants/SKU constraints, counter/event/idempotency rollback, concurrent subprocess mutations, actual schema-1 upgrade with preserved company data, identity-mismatch upgrade suspension, real Redis duplicate delivery, actor revocation, unknown event versions, bounded failures/replay and injected receipt-trigger database failure. Injected failure tests are not a claim of a production outage or full distributed fault-injection benchmark.

## Files, setup and remaining gates

See the [canonical inventory](FILE_INVENTORY.md), [implemented module catalog](../modules/MODULE_CATALOG.md), [database ownership](../architecture/DATABASE_DESIGN.md), [setup/runbook](M03_SETUP.md) and [OpenAPI contract](../api/API_SPECIFICATION.yaml). Main additions are Platform/ModuleRegistry, Shared/MasterData and Shared/Events, Parties/Catalog module implementations/contracts/migrations, the master-data controller/routes, React page/layout/tests, operator commands and integration fixtures. M02 test fixture extraction shares safe isolated setup/cleanup without duplicating its tests.

The requested Infovish local workspace is upgraded with data preserved. Its included modules begin inactive for owner activation after MFA; no sample contacts/products are inserted there. Authenticated UI verification uses a separately created disposable tenant/identity, removed after verification before handoff. Owner credentials remain in the existing ignored local access file; no secrets belong in this report or source.

Deferred: CRM and all M04+ workflows; inventory/accounting/financial transactions; comprehensive price lists, conversions, fuzzy deduplication/merge, import/export, feature-flag management, arbitrary hooks/partner code, subscription billing, global cross-module search, external notifications/subscribers, fleet upgrades, production scheduling/monitoring/retention/recovery, broad accessibility and penetration tests. Mutations serialize against the per-tenant module lifecycle lock for correctness; production throughput is unmeasured. The internal journal has one consumer; future consumers require their own event/consumer deduplication and transactional effects. Application and database administrators remain privileged trust boundaries. TLS, managed secrets, backups/restore and production security review remain required release gates.

Recommended next action after verified completion: owner activates the desired modules and enters real records. M04 requires explicit authorization.
