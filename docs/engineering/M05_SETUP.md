# M05 Sales setup and operations

Continue from M04. M05 code requires PHP 8.4+ with BCMath; selected local/container/CI runtime remains PHP 8.5. Dompdf 3.1 generates fixed escaped PDFs without remote resources, PHP or JavaScript. Do not accept arbitrary templates/assets.

```powershell
docker compose build
docker compose up -d --wait
./scripts/platform-local.ps1 Upgrade infovish
```

Use the documented registry mirror fallback if Docker Hub is unavailable. Upgrade is additive schema 4, preserves existing company/master/CRM data and activation/entitlement revocations, installs Sales inactive, and supplies operator credentials only transiently. Runtime web/worker roles cannot perform DDL; orders/history allow SELECT/INSERT only. Back up before non-disposable upgrades. Container file sessions can require signing in again.

After privileged MFA, activate Contacts → Products & services → CRM → Sales under Contacts & catalog / Modules. CRM depends on Contacts; Sales depends on all three. Activation performs no migration. Company-granted members/auditors can read Sales but cannot mutate. Owners/administrators with MFA manage Sales; CRM opportunity selection still enforces its own assignment rules.

Open Sales → Sales tax profile. Enter the actual seller legal name/address and declare actual VAT registration; registered suppliers require nine-digit TIN, certificate number and effective date. LKR companies only. The real local workspace is never assigned invented registration details. See [source-reviewed rules](SRI_LANKA_TAX_RULES.md). No automatic registration/exemption inference; selecting zero/exempt requires a legal basis. Financial-services VAT, SSCL liability/filings, customs, withholding and special/deemed supplies are excluded.

Create a quotation for an active customer and 1-50 active product/service variants. Search customer/product choices (25 results). Optional open opportunities must belong to the same customer and be readable through CRM. Enter VAT-exclusive price, positive quantity and percentage discount explicitly; the UI does not silently copy a price. Inputs allow six decimals; the server rounds each discounted net line half-up to cents, sums standard-rated net, computes VAT18 once half-up to cents, then adds net+VAT. Values remain strings; total range is 22 integer/two fractional digits. This is a documented quotation rounding policy, not a statutory universal method.

Save/edit drafts using current revisions. Draft saving refreshes master/profile snapshots and totals; issue freezes them. Issuing records an internal action only, without sending email or messages. Expired quotations cannot issue/convert. Cancel a draft/issued quotation with a reason. Confirm an issued quotation to create exactly one immutable SO order with copied prices/tax snapshots. Current active references and unchanged tax-profile revision are checked; a changed profile requires revising a draft or cancelling/replacing an issued quotation. Changes to active catalog prices/names never reprice issued quotations. Matching retries with the same issued revision return the same order even with another key. Different revisions conflict. Cancelled/converted/confirmed documents cannot reopen, edit or delete. Orders create no invoice/payment/stock reservation/CRM update.

Download authenticated quotation/order PDFs from detail. Draft PDFs are labeled; all documents say 'Not a tax invoice.' Do not use them as evidence of tax-invoice compliance. Lists/search/filter are company-scoped, 25 rows per page; search covers reference/title/snapshotted customer name. Detail history shows latest 100 immutable rows; earlier rows remain stored. There is no full archive export/pagination yet.

```powershell
./scripts/platform-local.ps1 Test
./scripts/platform-local.ps1 Dispatch infovish owner@example.com
```

Use the active privileged actor email for dispatch. ID-only Sales events use the existing internal journal, not fulfillment/invoicing subscribers. Keep current profile and statutory rules under review; the snapshot rule version is LK-VAT-2026-10-30. No automatic legal update service exists. See [M05 report](M05_REPORT.md) and [R1 release gates](R1_RELEASE_GATES.md). Stop before M06.
