# M04 CRM implementation and verification

Status: IN PROGRESS pending hosted CI acceptance. Authorized by the user's continuation after M03 handoff on 2026-10-30. M05 remains unauthorized.

## Delivered scope

The [specification](M04_SPEC.md) elaborates PRD CRM/BR-CRM/AC-CRM lifecycle/conversion and related tenant, consistency, API, audit, precision and idempotency requirements. This is an implemented CRM baseline, not the entire P0-P2 CRM range or competitor parity. [ADR-021](../architecture/decisions/ADR-021.md) records scope, ownership, authorization, conversion and forecast decisions.

Implemented assigned company-scoped leads with normalized active-email duplicate rejection, qualification/loss/reopen and revision protection; configurable company pipelines/stages with explicit probabilities; opportunities with customer links, exact optional estimated values, stages, won/lost/reopen and immutable history; parent-scoped activities with UTC due times and retained completion/cancellation. Conversion uses Parties' public contract to create or explicitly select a customer and atomically create one opportunity with permanent lead links. Matching retries and concurrent attempts preserve one result; downstream failure rolls back the customer, opportunity, counters, audit, outbox and idempotency state.

CRM is a bundled schema-3 module depending on Parties. Existing master data remains compatible with schemas 2/3. Members manage assigned CRM records in explicitly granted companies; auditors are read-only; privileged MFA users configure pipelines, reassign, convert and reopen. Other modules retain the original privileged mutation default. Public immutable OpportunityData enforces the same read boundary. CRM member-authored events revalidate active company membership before internal journal receipt; no future business action or external subscriber is implied. Queue/audit metadata excludes prospect fields, notes and amounts. Query exception reporting now omits SQL text/bindings/private detail strings and retains only connection/SQLSTATE diagnostics, with a regression.

Forecasts are labeled estimates using authorized open records, explicit stage probabilities and PostgreSQL exact NUMERIC arithmetic. Each nonnegative weighted value rounds half-up to six places before summation. Known/unknown amounts are counted explicitly; no accounting revenue, tax, exchange conversion or commission behavior is implemented.

The Figma CRM frame was inspected: navy navigation, compact header, pale workspace, board/list controls, stage columns and white opportunity cards. The implementation follows those conventions using real authorized data. Illustrative accounts, revenue, win rates and average sales-cycle metrics were not copied. UI includes module/empty/loading/error states, lead/opportunity forms, explicit conversion/customer selection, pipeline/stage settings, record/activity/history detail, filters and a paginated board/list.

## Verification ledger

| Check | Result |
|---|---|
| Initial CRM integration | PASS: 8 tests / 76 assertions |
| Prior full regression with initial CRM cases | PASS: 56 tests / 397 assertions |
| Final expanded host suite | PASS: 65 tests / 435 assertions on PHP 8.5.10 |
| PHP formatting / PHPStan level 6 / module boundaries | PASS, including the post-lock authorization change; three bundled modules with declared contract seams |
| Frontend typecheck/lint/Vitest/build | PASS: 14 tests across 4 files |
| OpenAPI validation | PASS, version 0.4.0 CRM routes/schemas |
| Docker build and complete isolated suite | PASS: 65 tests / 435 assertions on PHP 8.5.12; app/worker healthy and no persistent provisioning credentials |
| Real authenticated browser and local schema upgrade | PASS: disposable tenant lifecycle, conversion, activities, exact forecasts, mobile navigation and 12 worker-delivered events; actual Infovish tenant upgraded to schema 3 with preserved records |
| Dependency audits | PASS: Composer validation/audit and npm audit, no reported advisories |
| Local secret scan | PASS: Gitleaks 8.30.1, all 222 Git-includable files, no leaks |
| Hosted CI / history secret scan | Initial history scan passed; rerun pending after formatting correction |

The first hosted run stopped at Pint: two CRM files retained spacing differences masked by the local formatting cache. Fresh-cache formatting corrected only whitespace, and an independent fresh-cache check passed. The full container suite above passed before that whitespace-only correction. Final readiness and registered owner sign-in both returned HTTP 200.

Expanded tests cover assigned-member/auditor/company/tenant boundaries, duplicate IDs, inactive entitlement/module/MFA, CSRF, stale revisions, company-grant revocation while waiting for the mutation lock, explicit customer reuse/collision, conversion retry/concurrency, rollback after customer creation, exact large/small forecasts/rounding/unknowns, lost/won/reopen behavior, immutable history, activity parent/time/terminal state, schema-2 upgrade preservation and ID-only CRM member events through real Redis. Tests use dedicated infovish_test and uniquely named tenant databases/roles, with restricted runtime access and separate operator credentials. Earlier M02/M03 regression remains mandatory.

## Files, operation and limits

Main additions: CRM module manifest/migration, Domain contracts, Application services, Presentation controller, React CRM feature forms/details/page/tests, contract extensions in Parties, shared authorization/event/numbering/schema compatibility changes, privacy regression and integration fixtures. [File inventory](FILE_INVENTORY.md), [module catalog](../modules/MODULE_CATALOG.md), [setup/runbook](M04_SETUP.md), [database design](../architecture/DATABASE_DESIGN.md) and [OpenAPI](../api/API_SPECIFICATION.yaml) provide the reviewable artifact set.

The requested local tenant is upgraded to schema 3 with existing organization/master data and activation state preserved; CRM begins inactive with no fabricated records or pipeline probabilities. Browser acceptance used a separate disposable identity/tenant, removed after verification with guarded database/role identifiers. Twelve events were confirmed delivered in the UI through the real running worker. Conversion produced an opportunity of 123.456789; changing its stage probability to 80% produced 98.765431. Closed and reopened history and completed activities were verified. The UI now clears a previous forecast during refresh, covered by a deferred-response regression. The final image restart requires local users to sign in again. Owner credentials remain private in ignored local storage.

Deferred: M05 Sales and first commercial release, external capture, fuzzy merging, territory/account plans, AI scoring/churn, advanced forecast snapshots, complete activity/history archive pagination, calendar/message delivery, commercial billing, third-party modules/hooks, fleet scheduling, production monitoring/retention, accessibility certification, load/security assessment and backup restoration gates. Detail previews cap at 100 planned/recent activities and latest 100 immutable history rows; all earlier rows remain stored. Master-data Contacts read permissions remain company-wide as in M03, independently of assigned-only member CRM reads. Tenant-wide mutation serialization is a correctness baseline with unmeasured production throughput. No requirement is Released.

Recommended next action after acceptance: owner activates Parties/CRM and configures real company pipelines/probabilities. Stop before M05 until explicitly authorized.
