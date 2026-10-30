# M06 Finance setup and operation

Continue from M05. Read [scope](M06_SPEC.md), [tax review](M06_TAX_REVIEW.md) and [release gates](R1_RELEASE_GATES.md). Finance is bundled 1.0.0, tenant schema 5, LKR only. Installation creates no chart, period, tax registration, opening balance or transaction.

```powershell
docker compose build
docker compose up -d --wait
./scripts/platform-local.ps1 Upgrade infovish
```

The existing mirror fallback is `docker compose build --build-arg IMAGE_REGISTRY=mirror.gcr.io/library`. Back up before upgrading non-disposable data. The additive operator upgrade preserves earlier records/activation and entitlement revocations, installs Finance inactive, and exposes no provisioning credentials to persistent web/worker environments. Container restarts may require signing in again.

With owner/administrator MFA, enable Contacts, Products & services, CRM, Sales, then Finance in Contacts & catalog / Modules. Finance depends on Parties and Sales; Sales retains its transitive dependencies. Owners/administrators with MFA mutate; company-granted auditors read; ordinary members cannot access Finance. Activation performs no DDL. Open Finance and select the authorized company.

In Setup, enter the accountant-reviewed chart with unique stable codes and types. A bank account is an asset. Explicitly map six distinct non-bank active accounts: receivable asset, payable liability, revenue income, expense expense, input VAT asset and output VAT liability. These mappings cannot change after posting. Account name/active changes require the observed revision; code/type/bank classification is stable. Enter actual seller legal name/address and registration details, plus an issuer code of 1–15 uppercase letters/digits containing at least one digit. Do not copy synthetic verification particulars. Seller fields must agree exactly with the confirmed Sales order snapshot.

Create explicit non-overlapping periods covering posting dates. A locked period rejects postings/reversals. Lock changes need a reason/current revision; only the owner may reopen. There is no fiscal-year closing or opening-balance import in this baseline. Do not bypass control-account restrictions to improvise migration entries.

Journal drafts require 2–100 lines, active same-company accounts and positive equal debits/credits at posting. Enter decimal strings with at most two places; each line has one positive side. Manual receivable/payable control entries are blocked. Posted journals/lines are immutable, including adding lines later. Manual corrections append a single reasoned reversal in an open period. Automatic invoice/payment entries use their own workflow and cannot be reversed through the manual journal route.

Customer invoice drafts take the full value of one immutable confirmed Sales order. Enter actual purchaser legal name/address, supply date, issue date and due date. Ordinary VAT18 tax invoices additionally need registered purchaser TIN, confirmed timely written-request evidence, effective seller registration and a supply within the supported 28-day window. Only all-standard-rated orders qualify. Post customer invoices on the actual current date (Asia/Colombo). Mixed/special regimes are blocked. Commercial non-tax invoices require zero VAT; a positive-VAT order cannot be relabeled commercial. No partial billing, repricing, credit/debit notes or posted invoice cancellation is provided.

Posting atomically assigns the invoice serial, journal and immutable snapshot. Ordinary tax serials use `YYMMM_issuer-code_continuous-series`, with no automatic sequence reset. Dates print MM/DD/YYYY and LKR totals to cents. Original issuance is unique; duplicate and reasoned COPY ONLY requests create retained immutable PDF records. Stable retries return byte-identical stored PDFs verified against SHA-256. Fixed escaped templates disable remote assets/PHP/JavaScript. Printing requires authentication, CSRF and an idempotency key; there is no external sending. Detail shows the latest 100 print records. Record retention/backup ownership remains an operations gate.

Supplier bills record an active supplier, unique supplier reference, description, dates, explicit net and recoverable VAT. Nonzero VAT requires effective declared company registration and evidence of eligibility reviewed by the operator. The application does not determine tax-credit entitlement. This baseline posts expenses only, not inventory or capital assets. Draft bills may be revised/cancelled; posted bills are immutable.

Record a receipt/payment against one posted invoice/bill using an explicit bank, posting date, reference and amount. Partial allocations are supported; overpayment and wrong direction are rejected. This records accounting entries and does not transfer money. A reasoned payment void appends an opposite journal and void record; reconciled entries cannot be voided. Document outstanding and historical aging use effective payments/void dates.

For reconciliation, create a manual dated bank statement with signed lines, explicit opening/closing and exact ledger-line matches. The first statement requires a confirmed cleared opening that equals the pre-statement book balance; pre-baseline outstanding items are unsupported. Each subsequent statement starts after the prior end and carries forward its closing. Each nonzero line matches one same-bank ledger entry with the same sign/amount and eligible date. Opening plus lines and adjusted book balance must both equal closing; no plug is generated. Earlier unmatched book entries can clear on a later statement. Post bank charges separately before matching. Finalization freezes statement/matches and prevents backdated bank entries into that window. Eligible bank-line choices cap at 500 and statements at 100 lines.

Reports show posted trial balance, account ledger (25 per page), period income/expense, position with cumulative unclosed result and invoice/bill aging. Aging totals cover all open documents, with a 100-document preview. These are management views, not complete statutory financial statements or certified IFRS/SLFRS output. No FX, consolidation, closing, bank feeds, gateways, automatic VAT return, SSCL, withholding or special financial-services tax is implemented.

```powershell
./scripts/platform-local.ps1 Test
./scripts/platform-local.ps1 Dispatch infovish owner@example.com
```

Use the actual authorized actor email for dispatch. ID-only Finance events use the internal receipt consumer. See [API 0.6.0](../api/API_SPECIFICATION.yaml) for route fields, revision/idempotency requirements and errors. Stop before M07.
