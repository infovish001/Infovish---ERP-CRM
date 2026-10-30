# R1 CRM and Sales commercial release gates

The PRD names M05 as the first commercial release, with quote-to-order tests as its functional exit gate. It also requires measured load/security and successful restoration evidence before production-readiness claims. Passing a development milestone does not satisfy every R1 deployment gate. No public release/deployment is performed by the local development continuation.

| Gate | Required evidence | Current status |
|---|---|---|
| Functional CRM/Sales | Tenant-scoped lifecycle, exact quote pricing, conversion and retries, immutable history, authenticated PDFs | PASS: M04 verified, M05 host/container/browser/CI acceptance in M05_REPORT |
| Reproducibility/CI | Locked dependencies, host/container suites, static/API/boundary checks and secret/dependency scans | PASS: M06 host/container 90 backend tests / 654 assertions, 26 frontend tests, static/API/build/scans and implementation CI; see M06_REPORT |
| Localization applicability | Actual company registration, classifications and legal basis, accepted quotation rounding policy; unsupported regimes excluded | Rules sourced from IRD at user request; business-specific configuration/review remains required before use |
| Document compliance | Distinguish quotation/order from tax invoice; reviewed invoice specifications before invoice release | M06 adds narrowly sourced ordinary VAT18 invoices and retained PDF issuance; business applicability, statutory exceptions/corrections and retention review remain required |
| Recovery | Restorable encrypted tenant/platform/object backups, key recovery, ownership, retention and timed production-like drill | Production-like coordinated restoration NOT VERIFIED; M06 local synthetic drill matched all 36 table counts/content digests, 13 Finance/three Sales guards and retained PDF hashes in 35.052 seconds, limited to one disposable tenant; prior M05 evidence retained in its report |
| Security assessment | Scoped deployed identity/isolation/IDOR/CSRF/PDF checks, resolved critical findings, reviewed infrastructure/secrets/TLS | Automated development checks only; deployed assessment and independent review NOT VERIFIED |
| Performance/capacity | Defined concurrent workload, selected HTTP endpoints/p95, PDF/memory/queue/DB-lock capacity | NOT VERIFIED on a representative deployment; no SLA/p95 guarantee |
| Operations | Staging, managed DB/Redis/secrets/storage/TLS, dispatch scheduling/alerts, monitoring, incident/restore/upgrade runbooks and operator ownership | Local Docker only; production environment/ownership not configured |
| Dependency distribution | Retain upstream notices/licenses and review distribution obligations | New PDF dependencies report LGPL-2.1, LGPL-2.1-or-later, LGPL-3.0-or-later and MIT in the lockfile; bundled licenses remain present; distribution-specific review pending |
| Delivery governance | Approved release version/scope and production rollout/rollback with schema compatibility | R1 release NOT APPROVED/NOT PUBLISHED; M06 authorized for local implementation, M07 remains gated |

Release status: NOT RELEASED. No production guarantee, competitor parity or certified tax compliance is claimed. Outstanding gates are explicit deployment/business inputs, not hidden defaults or placeholder product features. The functional milestone can be verified while R1 remains a release candidate with these gates open.
