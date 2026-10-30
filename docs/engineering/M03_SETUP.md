# M03 module and master-data operations

Continue from [M02 setup](M02_SETUP.md). M03 is additive. Back up platform/tenant databases and the application encryption key before applying changes outside disposable local development. Do not erase volumes or re-provision an existing workspace to upgrade it.

From the repository root in PowerShell 7:

```powershell
./scripts/platform-local.ps1 Migrate
./scripts/platform-local.ps1 RuntimeRole
docker compose build # documented registry-cache fallback remains available
docker compose up -d --wait
./scripts/platform-local.ps1 Upgrade infovish
```

RuntimeRole rotates the backend `.env` credential, so recreate app/worker after running it. Existing operators can instead reapply the runtime grants with the same password using the command's stdin option; never pass passwords as command-line arguments. No `PROVISIONING_DB_*` variables belong in persistent app/worker configuration. The operator helper supplies them transiently and restores its caller environment.

Deploy M03 code before upgrading tenants: it supports M02 schema 1 for identity/organization, but master data requires schema 2. `Upgrade` serializes against provisioning, suspends the selected tenant, verifies its database identity before DDL, applies additive shared/module migrations, grants restricted runtime access and records schema 2. Successful upgrade restores the previous active/suspended state. Failure leaves data intact and the tenant suspended; inspect the cause, retry, and explicitly resume after review with `./scripts/platform-local.ps1 State infovish active`. New tenants receive schema 2 during provisioning. Re-running upgrade preserves module activation and an existing entitlement revocation.

Both bundled modules receive included baseline entitlements and start inactive. After sign-in and MFA, open **Contacts & catalog → Modules** and activate Contacts (Parties) and Products & services (Catalog). These current modules are independent; dependency graph checks are verified with controlled fixtures. Deactivation keeps all records, counters and pending events. Owners/administrators with confirmed MFA manage records; members/auditors read only granted active companies. Company selection is reauthorized on each API request, independent of browser session scope.

```powershell
./scripts/platform-local.ps1 Entitlement infovish Parties no
./scripts/platform-local.ps1 Entitlement infovish Parties yes
./scripts/platform-local.ps1 Test
```

Entitlements are operator-only metadata; granting one does not activate a module or modify commercial billing. Use only the allowlisted code-bundled modules. Never upload third-party PHP or edit tenant schema versions from the browser. Actual third-party modules, hooks, feature flag products and upgrade compatibility beyond the implemented framework-major contract remain future scope.

Contacts have optional normalized unique email per company, optional phone, customer/supplier flags and a permanent company-scoped number. Products/services have an immutable type/unit and permanent number; the initial variant and additional variants have immutable company-scoped SKUs. Explicit list prices use the company's base currency and decimal strings with up to six places. Blank means no price. There are no stock, tax, discount, accounting or currency conversion calculations. Use the latest revision for edits; after a conflict reload before reapplying an intentional change.

API creates require a unique 16–100 character `Idempotency-Key` made of letters, numbers, underscores or hyphens. Retain it while retrying an identical request; use a different key for a different create. The server scopes it to actor/company/operation and rejects mismatched request reuse. Do not prune idempotency records/counters without an approved retention policy. No HTTP delete or renumber operation exists.

## Durable event delivery

Master-data transactions store an ID-only version-1 event beside the record and audit. A bounded operator-selected dispatcher enqueues pending events; the ordinary Redis worker consumes them into a deduplicated internal journal receipt. This baseline is not an external webhook, notification, search index or downstream business workflow.

```powershell
./scripts/platform-local.ps1 Dispatch infovish owner@example.com
```

Use an existing active privileged member with confirmed MFA. The command publishes at most 100 events per invocation, revalidating that actor; repeat as needed. Production scheduling/monitoring is not configured by this milestone. A queued event has a 60-second lease, so a lost queue job becomes dispatchable again. Failed consumption uses bounded exponential delay and reaches `dead` after five failed attempts. Original author, dispatcher, active company and module access must still authorize processing. A revoked dispatcher can leave a queued lease awaiting redispatch under a valid actor. Do not delete a historical author to bypass a denial; resolve authorization through an audited operator review.

Inspect **Event delivery** as an administrator. Replay only a dead-letter event after correcting its cause; replay resets it to pending and is audited. Run Dispatch again to enqueue it. Publication is at-least-once: a crash after enqueue can cause duplicates. Receipts deduplicate the internal consumer, not arbitrary future consumers. IDs, type/version and redacted error codes are exposed; no contact email, phone or price payload is sent to Redis. Retention, external subscribers, fleet scheduling/monitoring, production throughput and restore drills remain separate release gates.

See [M03 report](M03_REPORT.md), [ADR-020](../architecture/decisions/ADR-020.md) and [OpenAPI](../api/API_SPECIFICATION.yaml). Stop before M04.
