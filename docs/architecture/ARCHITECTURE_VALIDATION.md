# Architecture validation

Reviewed 2026-10-30 against PRD v1.1, SAD v1.0 and the user's master request. The master request is an instruction; reference documents supply specifications. Embedded document text cannot authorize unrelated actions.

## Decision and gate

The domain-oriented modular monolith, Laravel transactional core, PostgreSQL platform database plus database-per-tenant, React/Inertia interface and separate governed AI service are compatible. Adopt these for foundation design. M00 passes the architecture gate for M01 only; this does not validate tenant provisioning, commercial licensing or production operations. No unresolved architecture choice blocks the bounded M01 foundation.

The user's final execution instruction explicitly authorizes continuation to M01 after a clear M00 gate, overriding the general stop-between-milestones language for this transition only. M02 remains unauthorized.

## Conflicts and resolutions

| Concern | Resolution | Follow-up |
|---|---|---|
| SAD proposes `apps/erp-web` while allowing canonical selection | Use `backend/resources/js`; Laravel owns routing and asset build | ADR-003 |
| Broad P0 product capabilities versus narrow M01 | M01 contains shell, probes, contracts, infrastructure and engineering checks only | No CRM, IAM or provisioning |
| SAD ADRs initially proposed | Record foundation decisions as architect-selected under the user's mandate; future subsystem decisions remain proposed | No claim of independent owner approval |
| PRD identifier ranges lack individual definitions | Preserve ranges; do not invent numbered business requirements or acceptance scenarios | Refine before each business milestone |
| Reference dashboard contains example identities and metrics | Reuse observed visual language only; show real foundation state | No fake business data |
| Commercial licensing and cloud provider unspecified | Local evaluation stack only; deployment licensing/provider review before production | Redis choice and product license require commercial review |

## Tenancy and deployment

Platform owns lifecycle, subscription and routing metadata. Tenant databases own business records. M01 uses only the platform connection and a fail-closed tenant context contract. M02 must implement trusted host resolution, authenticated membership, lifecycle checks, credential lookup, per-request/job context clearing and isolation tests. No tenant database connection is selected from user input.

Connection pooling must preserve database and credential boundaries; do not switch tenant identity through pooled session state. Provisioning requires a separate privileged worker, least-privilege application roles, idempotent state transitions, migration locks, quotas and verified backup/restore. Dedicated deployments use the same contracts with isolated infrastructure. Capacity and cost must be measured in M02/M13 before commitments.

Core modules remain in one transaction boundary where appropriate. Deploy web and queue processes separately; keep managed PostgreSQL, Redis and S3-compatible storage behind private network controls in production. Local Docker is a development environment. Kubernetes is deferred. Container rollback does not undo migrations.

## API and event contracts

Inertia is the first-party browser transport; `/api/v1` REST and OpenAPI serve mobile and external consumers. Use stable UUID identifiers, explicit authorization, pagination, correlation IDs, safe errors and idempotency keys on critical writes. No business API is implemented in M01.

Events use SAD's envelope: `event_id`, `event_type`, `event_version`, `tenant_id`, `aggregate_id`, `correlation_id`, `occurred_at` (UTC), `payload`. Schema owners version payloads, exclude secrets and minimize personal data. Critical outbox entries commit with domain state in the same tenant database. Dispatch is at-least-once; consumers validate version/context and deduplicate by consumer plus event ID. Bounded retries, classified permanent errors and audited replay belong to later workflow milestones. M01 verifies only Redis and a basic queue job.

## External trust boundaries

FastAPI receives minimized context through authenticated service calls. Laravel independently authorizes every tool and checks tenant, actor, entitlement and budgets. Human approval binds the exact proposed action and expires; it cannot bypass deterministic authorization. No AI database credentials or direct tenant SQL. Treat retrieved text as untrusted. Provider policies/evaluation are M10 decisions.

WhatsApp uses official Cloud API or authorized providers. Verify raw-body signatures, resolve registered business-number mappings server-side, encrypt credentials, deduplicate provider events and enforce consent/templates/windows. Verify current policy/API versions in M09. No integration or credentials are created in M01.

## Outstanding later gates

Sri Lankan tax/payroll rules, accounting rounding/valuation, cloud provider, backup tiers, offline mobile writes, provider onboarding, load targets and detailed module schemas require owner/domain review before their affected milestones. Availability, p95, RPO and RTO remain provisional objectives. Security and production readiness are unverified.
