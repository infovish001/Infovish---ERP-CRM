# M06 Finance and Accounting verification

Implemented 2026-10-31 under the continuation after verified M05. Scope is [M06_SPEC](M06_SPEC.md), [ADR-023](../architecture/decisions/ADR-023.md) and [source-reviewed invoice rules](M06_TAX_REVIEW.md). Stop before M07. R1 remains NOT RELEASED.

## Delivered behavior

Finance 1.0.0 / tenant schema 5 adds explicit LKR chart/mappings/periods, balanced append-only journals and reasoned manual reversals; full-order customer invoices and declared supplier expense bills; retained original/duplicate/COPY ONLY PDFs; partial recorded receipts/payments and append-only voids; exact manual bank reconciliation; posted trial/account-ledger/income-expense/position/aging management views. Ordinary members are denied Finance, company-granted auditors read, and owner/administrator mutations require confirmed MFA. No setup data, tax registration, balances or transactions are fabricated in the actual organization.

Cross-module billing uses Sales BillingData and Parties DTOs. Exact amounts remain decimal strings. Deferred database constraints require balanced positive posting, valid open periods and bank windows; a database-generated transaction ID rejects later balanced line extension. Immutable grants/triggers protect posted records. One transactional mutation coordinates source references, counters, journal, audit/outbox and idempotency. PDFs retain byte-identical content and SHA-256, with no remote assets, arbitrary templates or external sending. Payments record ledger effects only, without moving money.

## Verification evidence

| Check | Result |
|---|---|
| Finance integration | PASS: 11 tests / 91 assertions on host PHP 8.5.10, real PostgreSQL/Redis |
| Complete host regression | PASS: 90 tests / 654 assertions, PHP 8.5.10 |
| PHP static/boundary/format | PASS: PHPStan level 6, five business-module boundaries, independent fresh-cache Pint verification |
| Frontend | PASS: 26 tests / six files, TypeScript, ESLint and production build |
| API | PASS: OpenAPI 0.6.0 with Finance fields, authorization, conflicts and PDF issuance |
| Dependency audit | PASS: Composer strict validation/audit and npm audit, no reported vulnerabilities |
| Docker | Corrected images built; final container regression pending |
| Browser/PDF | PASS: invoice draft/post preserves 277.78 net + 50.00 VAT = 327.78, authenticated original download and duplicate/copy history, 100.00 partial receipt with 227.78 remaining, finalized reconciliation with 100.00 book/statement and 0.00 outstanding; posted reports balance. Mobile 390px, navigation and dark theme verified. All seven pages across original/duplicate/copy and four-page fifty-line invoice visually reviewed: labels, TINs/dates, exact totals, repeated table headers and page references legible, no clipping |
| Real worker | PASS: all 35 synthetic workspace events delivered, including 24 Finance events |
| Actual tenant upgrade | PASS: backups of local tenant/platform saved before additive schema-5 upgrade; all 22 existing business-table row counts/content digests match; earlier activation preserved, Finance inactive and all twelve new tables empty |
| Local secret scan | PASS: Gitleaks 8.30.1, all 278 Git-includable files, no leaks |
| Synthetic restore | PASS: all 36 table counts/content digests match, 13 Finance guards and three Sales guards restored; retained PDF SHA-256 values verified; dump/restore/check phase 35.052 seconds |
| Hosted CI | Pending implementation push |
| Commercial release | NOT RELEASED; R1 gate ledger remains open |

Integration coverage includes equal debits/credits, period locks, AR/AP control restrictions, immutable lines and later extension, manual reversal and automatic-source restrictions, invoice/payment/void/aging totals, supplier input-VAT evidence, source idempotency/concurrency and overpayment races, injected ledger-failure rollback, Redis delivery, tax particulars/copy rules, schema-4 upgrade/entitlement preservation, company/tenant/role/MFA/CSRF and reconciliation outstanding/backdating. PDF bytes/hash and stable issuance retries are checked. Final test runs use the restricted runtime role; operator credentials remain transient.

The first full run was invalidated by a system-drive space incident and unresponsive PostgreSQL/Redis. Only the verified regenerable PHPStan temporary cache was removed; its cache now uses writable project storage on D:. Docker restarted with a recurrence of the previously documented stale runtime sockets. The two verified socket-only directories were preserved under backup names before relaunch. No volume/virtual-disk reset or user database deletion was performed. Existing service volumes/images were verified, PostgreSQL recovered and both database services became healthy. The subsequent Finance-specific and complete host suites passed; the results above supersede the interrupted run. Two exact identity-verified empty test databases/roles left by the interruption were cleaned up. Static analysis also found a PDF validation variable error, and review found an invalid supplier-VAT supply-date lookup; both were corrected before final acceptance. Browser review also led to clearing correction reasons after successful operations and showing a readable match status instead of an internal UUID; all frontend checks passed again on the final source.

## Operations and limits

Use [M06_SETUP](M06_SETUP.md) for activation and actual accountant-reviewed company configuration. The actual Infovish owner identity/MFA were preserved. Local backup archives are private ignored runtime artifacts, not coordinated production/key/object-store recovery evidence. Browser tests use a disposable synthetic tenant and registration details, never actual tax filings or financial transfers. The synthetic restore used a custom-format dump into a newly named guarded disposable database, compared ordered content digests/counts for every public table and verified retained PDF hashes/Finance triggers, then removed only the restore target. It does not close the coordinated production recovery gate.

Ordinary all-standard VAT18 tax invoices only; effective registered seller/purchaser particulars, timely-request evidence and explicit supply assertions are required. Other zero-VAT orders may use commercial invoices. Mixed/special/financial VAT, SSCL, withholding, filings and automatic input-credit eligibility are excluded. No statutory credit/debit notes, returns, partial billing, FX/consolidation, fiscal-year closing, opening migration, assets/inventory costing, automatic bank/gateway integration or external delivery. Reports are management views, not full statutory statements or IFRS/SLFRS certification. Ledger serialization throughput, broad large-document capacity, deployed security/localization assessment and coordinated recovery remain R1 gates. Aging/print previews and bank-choice limits are explicit in setup/API documentation. No requirement is Released.
