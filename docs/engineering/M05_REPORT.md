# M05 Sales implementation and verification

Status: PASS for the M05 Sales functional baseline; R1 commercial release is NOT RELEASED. User continuation after M04 authorizes M05 only; internet-researched Sri Lankan tax rules explicitly requested. Stop before M06. R1 commercial release remains gated in R1_RELEASE_GATES.md.

Implementation commit: `bcf168e1fe656c0a8752af0ab252a23c85699bfd`. [Hosted acceptance](https://github.com/infovish001/Infovish---ERP-CRM/actions/runs/41105714221) passed 78 backend tests / 562 assertions, 20 frontend tests and all static/API/build/dependency/history-secret checks.

## Scope and decisions

[M05_SPEC](M05_SPEC.md) refines grouped PRD SAL, E2E-004/005 and cross-cutting consistency/isolation/precision/API/event requirements. [ADR-022](../architecture/decisions/ADR-022.md) records Sales ownership, explicit company read permissions, public contract dependencies, frozen snapshots, atomic permanent conversion, decimal policy and fixed PDF security. This is a quotation-to-order baseline, not every P0-P2 Sales feature or production certification.

Schema 4 adds Sales tax profiles, quotation rows with ordered JSON line snapshots, immutable orders and history. Parties/Catalog/CRM expose authorized public DTO lookups; Sales does not query their private persistence. Privileged MFA users mutate; company-granted members/auditors read. Ordinary Sri Lanka VAT18 and operator-selected zero/exempt/unregistered classifications use declared seller registration/effective dates and retained source/rule/profile revisions. [Tax research](SRI_LANKA_TAX_RULES.md) distinguishes the 2026 financial-services amendment and SSCL from ordinary VAT. No invented registration, supply classifier or automatic legal update.

Draft save validates and snapshots all commercial data; issue freezes content. Conversion creates exactly one confirmed order with original prices and taxes; rows, references, number counters, history, audit, outbox and idempotency commit together. Revisions, expiry, active references/profile compatibility and permanent uniqueness protect retries/concurrency. No silent reopen or historical repricing. Pricing uses BCMath strings, six-place inputs, net-line half-up cents and document standard-base VAT half-up cents. No stock/accounting/payment/CRM mutation occurs.

Figma Sales reference was inspected on 2026-10-30: navy sidebar, pale workspace, compact header, blue new-quotation action, recent-records table, search/filter and statuses. The implementation follows that layout with actual authorized rows. Illustrative revenue/completion metrics and invoice/fulfillment rows were not fabricated. React Sales includes module/profile/empty/loading/error states, company/search/filter/pagination, draft line editing and legal-basis controls, internal issue/cancel/convert, immutable detail/history and authenticated PDF links. Fixed escaped PDF templates disable remote resources, PHP and JavaScript and explicitly say 'Not a tax invoice.'

## Verification ledger

| Check | Evidence |
|---|---|
| Initial targeted integration | PASS: 6 tests / 81 assertions |
| Expanded host regression | PASS: full 76 tests / 543 assertions, then two additional CSRF/overflow/50-line pricing tests / 19 assertions; PHP 8.5.10 |
| PHP formatting/static/module boundaries | PASS: independent fresh-cache formatting verification, PHPStan level 6 and four declared module boundaries |
| Frontend type/lint/tests/build | PASS: final 20 tests / 5 files, typecheck, ESLint and production build |
| OpenAPI | PASS: 0.5.0 Sales schemas/routes |
| Docker build/complete container regression | PASS: final PHP 8.5.12 images, 78 tests / 562 assertions; app/worker healthy and persistent environments contain no provisioning credentials |
| Authenticated browser/PDF visual verification | Desktop PASS: profile/create/issue/convert, exact 277.78 net + 50.00 VAT = 327.78 order, authenticated PDF download; all ten long-PDF pages visually reviewed including repeated headers and page-reference footers. Mobile 390px/dark theme/navigation PASS; seven actual worker-delivered events confirmed in the UI; disposable tenant/identity removed after verification |
| Local actual tenant additive upgrade | PASS: existing Infovish tenant upgraded to schema 4; records/activation preserved, Sales inactive and no fabricated profile/data |
| Dependency audits | PASS: Composer validate/audit and npm audit; no reported vulnerabilities |
| Local secret scan | PASS: Gitleaks 8.30.1 scanned all 246 Git-includable files without leaks |
| Hosted CI/history secret scan | PASS: linked implementation run, 78 backend tests / 562 assertions, 20 frontend tests and all checks |
| Synthetic backup/restore drill | PASS: all 24 table row counts/content digests matched, all three Sales triggers restored; 18.68 seconds including dump/restore/data verification |
| R1 commercial release | NOT RELEASED: explicit gate ledger |

Tests use dedicated infovish_test and disposable PostgreSQL tenant databases/roles; operator credentials remain transient and runtime roles restricted. Expanded coverage includes lifecycle/rounding/classification, stale revisions, retries/concurrency, rollback after order failure, immutable database guards, catalog snapshot preservation, schema-3 upgrade, role/MFA/company/tenant/module/entitlement boundaries, PDF auth/escaping and real Redis receipts. The first full run had one incorrect test expectation (404 instead of the intended 403 for a foreign-tenant company), corrected without changing application behavior; the full rerun passed. Extra tests cover 50-line VAT aggregation, 100% discounts, overflow rollback and Sales-specific CSRF. The final container suite and hosted CI each passed all 78 tests / 562 assertions, including the final PDF footer. Independent fresh-cache Pint verification passed after formatting converged; no cached result was used as final evidence. The disposable browser tenant/identity and temporary restore database were removed with exact guarded identifiers after acceptance. Browser acceptance used synthetic seller registration only; the actual owner profile remains empty.

PDF dependency license metadata is retained in composer.lock and bundled vendor licenses; distribution-specific notice/obligation review is recorded in the R1 gate ledger.

The restore drill dumped only the guarded synthetic tenant to a custom-format archive in memory, restored into a newly named disposable database, compared every public table's count/ordered-content digest, verified Sales triggers, then removed the restore database. This is single-tenant data/schema recovery evidence, not a platform/key/object-store or production-like timed recovery claim. It follows the official [pg_dump](https://www.postgresql.org/docs/17/app-pgdump.html) / [pg_restore](https://www.postgresql.org/docs/17/app-pgrestore.html) approach; R1 recovery gates remain open.

## Operation and remaining scope

See [setup](M05_SETUP.md), [OpenAPI](../api/API_SPECIFICATION.yaml), [module catalog](../modules/MODULE_CATALOG.md) and [file inventory](FILE_INVENTORY.md). Actual local upgrade preserved organization/master/CRM records, left Sales inactive and tax profile unconfigured, and inserted no fabricated business records. Synthetic browser tenant/identity data was isolated and removed after acceptance; intermediate PDFs remain ignored local verification artifacts. Final readiness and registered owner sign-in returned HTTP 200. A read-only actual-workspace probe confirmed active schema 4, Sales disabled, and zero Sales profiles/quotes/orders. Owner credentials remain private in ignored local storage.

Deferred Sales: full price lists, currency conversion, tax-inclusive prices, automated exemption classification, special/deemed/financial/import/withholding/SSCL regimes, tax invoices/returns/filings, contracts/commissions/CPQ, external sending/signatures, order amendments/cancellation and fulfillment. Inventory/Finance effects require their later authorized milestones. History preview caps at 100. PDF localization beyond bundled fonts and large-document capacity need further assessment. Tenant-wide write serialization has unmeasured production throughput. Release gates cover deployment/security/load/recovery/operator ownership; no requirement is Released. Recommended next step after acceptance is owner module/profile setup with actual registration/classifications, then review R1 gates. Stop before M06.
