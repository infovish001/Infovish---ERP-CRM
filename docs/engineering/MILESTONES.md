# Milestone specification

| Milestone | Scope | Authorization |
|---|---|---|
| M00 | Architecture validation, source review, version compatibility, boundaries, ADRs | Authorized; gate recorded in M00 report |
| M01 | Engineering foundation described below | Authorized if M00 passes |
| M02 | Tenant provisioning, identity, organization, audit | Authorized by user continuation after M01; evidence in M02_REPORT.md |
| M03 | Module framework, shared services, catalog and contacts | Authorized after M02; M03_SPEC and M03_REPORT |
| M04 | CRM | Future |
| M05 | Sales and first commercial release | Future |
| M06 | Finance and accounting | Future |
| M07 | Inventory and procurement | Future |
| M08 | HR, projects, helpdesk, portals and documents | Future |
| M09 | Email, WhatsApp, communications and automation | Future |
| M10 | AI and document intelligence | Future |
| M11 | Manufacturing, POS, commerce, subscriptions and rental | Future |
| M12 | Studio and industry extensions | Future |
| M13 | Enterprise capabilities and cloud scale | Future |

M01 sequence: safely initialize Git and root guidance; scaffold Laravel; configure PostgreSQL and migrations; configure Redis/queue; add React/TypeScript/Inertia/Vite/Tailwind; implement Figma-aligned foundation shell; Docker Compose; minimal module convention; health/readiness; structured logs; backend tests; frontend lint/type/build/tests; boundary checks; GitHub Actions; secret/dependency scans; setup/troubleshooting; run/fix checks; update traceability; completion report.

M01 acceptance: reproducible setup; application boots; Inertia renders; real PostgreSQL connects/migrates; Redis connects and a real queued job runs; health responds; backend/frontend/boundary checks pass; CI passes when executed; no committed secrets; evidence recorded. Any required unverified result leaves M01 incomplete or conditional. Do not represent mocked dependency tests as integration evidence. Stop before M02.

The paragraph above preserves the original M01 gate. The user's subsequent continuation authorized M02. Its scope and acceptance scenarios are in [M02_SPEC.md](M02_SPEC.md), operations in [M02_SETUP.md](M02_SETUP.md), and actual verification in [M02_REPORT.md](M02_REPORT.md). Stop before M03.

The subsequent continuation after M02 authorizes M03 only. M03_SPEC defines the implementation baseline and M03_REPORT records evidence. Historical earlier stop gates above remain as provenance. Stop before M04.
