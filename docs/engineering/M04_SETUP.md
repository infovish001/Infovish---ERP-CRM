# M04 CRM setup and operating guide

Continue from [M03 setup](M03_SETUP.md). Deploy M04 code, then apply the additive schema upgrade through the operator helper. Preserve database/key backups before operating outside disposable development; do not erase or re-provision an existing workspace.

```powershell
docker compose build
docker compose up -d --wait
./scripts/platform-local.ps1 Upgrade infovish
```

The documented registry-cache fallback remains available. The helper supplies provisioning credentials transiently; none belong in persistent app/worker environments. M04 supports identity/organization on schemas 1-3, master-data modules on 2-3 and CRM on 3. New tenants install schema 3. The upgrade preserves companies, contacts, catalog records, existing module activation and entitlement revocations. Failure preserves data and leaves the tenant suspended for operator review/retry. Restarting development containers can require another sign-in because their file-backed sessions are ephemeral.

Sign in and complete privileged MFA. Under **Contacts & catalog → Modules**, activate Contacts (Parties), then CRM. CRM cannot activate without its dependency, and an active dependent prevents deactivating Contacts. Upgrade installs an included CRM entitlement but does not activate CRM, seed pipelines/leads, change billing or create sample business records. Operator entitlement control remains available:

```powershell
./scripts/platform-local.ps1 Entitlement infovish CRM no
./scripts/platform-local.ps1 Entitlement infovish CRM yes
```

## Working with CRM

Open **CRM → Pipeline settings**. A privileged MFA user creates a company pipeline with ordered stage names and explicit integer probabilities from 0 to 100. No stage probabilities are guessed. Pipelines/stages can be renamed or deactivated with revision protection; earlier history remains intact. Reactivate an appropriate pipeline/stage before moving or reopening an opportunity into it. Changing a stage probability changes current estimates, not past history or a historical forecast snapshot.

Create a lead with title/contact name and optional email, phone and source. The server allocates a permanent company-local LED reference. An active duplicate email is rejected generically within the company; administrators review duplicates without silently merging records. Members create for themselves and access only their assigned records in granted active companies. Owners/administrators with confirmed MFA can assign to other active writable company members; auditors can read granted companies without mutations. Contacts retains its M03 company-level master-data read permissions independently of CRM assignment restrictions.

Qualify a new lead. Loss requires a reason; privileged reopening of a lost lead requires a reason. A qualified lead can be converted by a privileged MFA user: explicitly choose a new customer or an existing active customer, then an active pipeline/stage and optional expected amount/date. New customer creation uses the Parties public contract. An existing email collision requires explicit customer selection or review/reactivation in Contacts; no automatic reuse or fuzzy merge occurs. Conversion commits customer, opportunity, lead links, counters, idempotency, audit and events together. The converted lead is read-only. It creates no quote/order/invoice.

Open opportunities can be edited and moved between active stages in their pipeline. Mark won/lost explicitly; loss requires a reason. Closed opportunities are immutable until a privileged user reopens them with a reason and valid active stage. These are CRM states and estimates, not accounting postings or recognized revenue.

Plan calls, meetings and tasks on an open lead/opportunity. Due times require an explicit timezone and are stored in UTC; the UI accepts/displays the user's local time. Notes are scoped to the parent record. Complete/cancel a planned activity; terminal activities remain immutable. Existing planned activities may be finished after the parent closes, while new/edit operations require an open parent. Detail views show up to 100 planned/recent activities (planned first, then recently closed), and the latest 100 immutable history entries; earlier rows remain stored. Full archive pagination, external calendar sync and notification delivery are deferred.

Lists and board pages contain 25 authorized records; board counts describe that page, while the list total describes matching records. Lead search covers title/reference/contact name/email; opportunity search covers title/reference. Forecast cards use all authorized open opportunities in the chosen company/pipeline, independently of list search/state filters. Expected amounts are optional nonnegative decimal strings, up to 14 integer and six fractional digits, in company base currency. Blank means unknown. Known and unknown counts are explicit; weighted amounts use the configured stage probability, round each nonnegative result half-up to six places, then sum using PostgreSQL NUMERIC. No float, currency conversion, tax, commission or accounting revenue calculation is performed.

## API and verification

See [OpenAPI](../api/API_SPECIFICATION.yaml). CRM routes are session-authenticated on the registered host, CSRF-protected and rate-limited. Creates/conversion require a 16-100 character `Idempotency-Key`; keep it for identical retries, change it for a different create. Updates/transitions require the current integer revision. Reload after a stale revision conflict. Matching conversion retries with a second key still return the original opportunity; a different conversion intent returns 409.

```powershell
./scripts/platform-local.ps1 Test
./scripts/platform-local.ps1 Dispatch infovish owner@example.com
```

Use the actual active privileged actor email for Dispatch. CRM mutations write ID-only version-1 outbox events; no prospect email, phone, note or expected amount enters Redis payloads. The internal journal validates CRM member authors' current active company membership and privileged dispatchers' MFA/access. It does not replay a CRM business action. M03 backoff/dead-letter/replay rules still apply. SQL error reporting now records only connection name/SQLSTATE, omitting SQL text, bindings and private database detail strings. Production monitoring, retention, restore/load/security assessment and external subscribers remain release gates.

See [M04 specification](M04_SPEC.md), [ADR-021](../architecture/decisions/ADR-021.md) and [report](M04_REPORT.md). Stop before M05.
