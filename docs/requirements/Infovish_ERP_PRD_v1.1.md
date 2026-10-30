INFOVISH ERP & CRM
Product Requirements Document (PRD) — Version 1.1
Document ID: INF-ERP-PRD-001
Date: 29 October 2026
Owner: Infovish Systems
Status: Product-level implementation baseline; detailed designs pending
Tagline: One Intelligent Platform. Every Business Operation.
1. Executive summary
Infovish ERP is a modular, multi-tenant enterprise resource planning and customer relationship management platform for small, medium and large organizations. It combines core business operations with governed AI, official WhatsApp Business integration, automation, customization and managed Infovish Cloud hosting. Odoo and Flectra are functional reference points; full parity is aspirational and must be assessed by product version and edition.
2. Objectives and product scope
OBJ-001 — Unified operational data and workflows
OBJ-002 — Modular adoption and controlled extensibility
OBJ-003 — Support multi-company and multi-branch operations
OBJ-004 — Configurable workflows and metadata-driven customization
OBJ-005 — Permission-governed AI assistance
OBJ-006 — Omnichannel communications
OBJ-007 — SaaS and dedicated deployment choices
OBJ-008 — Commercial subscription and entitlement management
OBJ-009 — Auditable financial and inventory integrity
OBJ-010 — Developer-friendly APIs and integration contracts
Out of scope for first commercial launch: verified feature parity with competitors, comprehensive manufacturing, advanced payroll localization, arbitrary third-party code execution, full offline ERP, and unreviewed autonomous AI transactions.
3. Personas, priorities and requirements conventions
Personas: platform operators, tenant owners, system administrators, executives, sales representatives, accountants, procurement officers, warehouse operators, HR managers, production staff, support agents, customers, suppliers, developers and integration partners.
P0 — platform foundation and first commercial launch
P1 — operational expansion
P2 — advanced capabilities
P3 — enterprise and industry ecosystem
Requirement identifiers remain stable across PRD, SAD, code, tests and release evidence. Statuses: Proposed, Approved, In Progress, Implemented, Verified, Released, Deprecated. Each requirement must be mapped to tests and a release.
4. Core platform and tenancy
TEN-001 through TEN-010  |  P0–P3
Tenant provisioning, isolation, trusted context, lifecycle, migrations, subscriptions, domains, entitlements, regional and dedicated deployments.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
IAM-001 through IAM-010  |  P0–P3
Authentication, RBAC, membership, sessions, auditing, MFA, field permissions, SSO and attribute-based authorization.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
ORG-001 through ORG-008  |  P0–P2
Companies, branches, departments, currencies, master data, numbering, fiscal calendars, intercompany and deduplication.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
MOD-001 through MOD-008  |  P0–P3
Module manifests, dependencies, entitlements, feature flags, upgrades, diagnostics, hooks and partner extensions.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
5. CRM and sales
CRM-001 through CRM-014  |  P0–P2
Leads, opportunities, pipelines, activities, assignment, forecasts, capture, territories, deduplication, AI scoring, churn indicators and account planning.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
SAL-001 through SAL-012  |  P0–P2
Products and services, quotations, PDFs, sales orders, pricing, fulfillment, invoicing, returns, contracts, commissions and CPQ.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
6. Finance and accounting
FIN-001 through FIN-016  |  P1–P3
Chart of accounts, balanced journals, periods, invoices, bills, payments, bank reconciliation, reports, tax, currencies, locks, fixed assets, budgets, forecasting, consolidation and treasury.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
7. Procurement
PRC-001 through PRC-012  |  P1–P3
Suppliers, requisitions, RFQs, purchase orders, goods receipts, returns, three-way matching, supplier balances, performance, contracts, reorder and supplier portal.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
8. Inventory and warehousing
INV-001 through INV-014  |  P1–P3
Catalog, warehouses, stock movements, availability, reservations, immutable ledger, counts, valuation, lots, serials, barcodes, replenishment, FEFO, landed costs and picking.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
9. Manufacturing
MFG-001 through MFG-012  |  P2–P3
BOMs, routings, work centers, production orders, consumption, outputs, costing, work orders, MRP, quality, maintenance, capacity and IoT.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
10. HR and payroll
HRM-001 through HRM-013  |  P1–P3
Employee records, organization, attendance, leave, onboarding, self-service, recruitment, salary structures, payroll, overtime, expenses, performance and workforce analytics.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
11. Projects and field service
PRJ-001 through PRJ-011  |  P1–P2
Projects, tasks, Kanban, timesheets, budgets, sales linkage, Gantt, resource planning, field jobs, service reports and profitability.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
12. Commerce and subscriptions
POS-001 through POS-006  |  P2–P3
Retail registers, payment capture, offline controls, loyalty and POS operations.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
ECM-001 through ECM-005  |  P2–P3
Storefront, checkout, payment gateways, order tracking and multistore.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
SUB-001 through SUB-004  |  P2–P3
Subscriptions, recurring billing, lifecycle management and usage-based billing.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
13. Marketing
MKT-001 through MKT-008  |  P1–P3
Segmentation, email and messaging campaigns, consent, journeys, analytics, landing pages, A/B testing and AI marketing assistance.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
14. Helpdesk and portals
HLP-001 through HLP-008  |  P1–P2
Tickets, queues, SLAs, omnichannel intake, knowledge base, surveys and AI support.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
POR-001 through POR-006  |  P1–P2
Customer and supplier self-service portals with strict external access scope.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
15. Document management
DMS-001 through DMS-010  |  P1–P3
Files, links, access controls, versions, OCR, extraction, approvals, signatures, contracts and retention.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
16. Logistics, fleet, rental and assets
LOG-001 through LOG-004  |  P2–P3
Logistics and delivery workflows.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
FLT-001 through FLT-003  |  P2–P3
Fleet operations and maintenance.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
RNT-001 through RNT-003  |  P2–P3
Rental agreements, availability and billing.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
AST-001 through AST-002  |  P2–P3
Asset management capabilities.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
17. Analytics and reporting
BI-001 through BI-010  |  P0–P3
CRM and sales dashboard, exports, financial and inventory reports, scheduling, KPIs, pivots, cross-module analytics, natural-language reporting and forecasting.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
18. Infovish Intelligence AI
AI-001 through AI-015  |  P2–P3
Tenant-aware orchestration, providers, authorized retrieval/tools, logs, approvals, OCR, CRM summaries, drafts, grounded analytics, budgets, forecasts, anomalies, workflows, voice and evaluation.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
19. Communications
COM-001 through COM-013  |  P1–P3
Notifications, email, templates, official WhatsApp accounts, signed webhooks, consent, CRM linking, shared inbox, mailbox sync, delivery tracking and AI support.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
20. Automation and Studio
AUT-001 through AUT-008  |  P1–P3
Events, approvals, triggers, conditions, actions, retries, execution history and visual workflow builder.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
STU-001 through STU-006  |  P2–P3
Custom fields, forms, dashboards, custom objects, packages and upgrade compatibility.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
21. Industry extensions
IND-001 through IND-010  |  P3
Real estate, hospitality, automotive, agriculture, construction, education, healthcare, distribution, professional services and nonprofit packs.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
22. APIs and cloud
API-001 through API-010  |  P0–P3
Versioned REST, standardized errors, authentication, OpenAPI, webhooks, idempotency, rate limiting, OAuth, import/export and developer portal.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
CLD-001 through CLD-012  |  P0–P3
Provisioning, secrets, migrations, backups, entitlements, metering, domains, monitoring, billing, quotas, dedicated and regional hosting.
Acceptance baseline: authorized users can perform each implemented capability under documented validation and lifecycle rules; data is tenant-scoped, audited where required, and covered by repeatable tests. Detailed per-requirement scenarios must be refined before implementation.
23. Cross-cutting business rules and acceptance criteria
BR-TEN-01–04: Every request/job must use verified tenant context; cross-tenant data access is forbidden; lifecycle actions must preserve data and traceability.
BR-CRM-01–04: Lead ownership, conversion, duplicate handling and stage changes must be permission-checked and auditable.
BR-FIN-01–06: Every posted journal balances; posted entries are not silently edited; corrections use reversals; locked periods reject unauthorized changes; decimal precision and local rules apply.
BR-AI-01–06: AI cannot bypass authorization; external model sharing follows policy; high-impact writes require approval; estimates are labeled; retrieved content is untrusted; failures degrade gracefully.
BR-INV: Stock movements are atomic; reservation and availability checks prevent overselling; immutable movement history is preserved.
BR-COM: Webhooks are authenticated and idempotent; credentials are encrypted and tenant-scoped; consent and provider messaging restrictions are enforced.
Representative acceptance tests: AC-TEN-01–04 verify provisioning and isolation; AC-CRM-01–04 verify lifecycle and conversion; AC-FIN-01–06 verify balancing, reconciliation, period locks and reporting; AC-AI-01–05 verify permission scoping, approval, prompt-injection resilience and provider failure handling.
24. Non-functional requirements
NFR-001: Tenant isolation and least privilege.
NFR-002: Transactional consistency.
NFR-003: Versioned, documented APIs.
NFR-004: Secrets and encryption controls.
NFR-005: CI and dependency scanning.
NFR-006: Safe database migrations.
NFR-007: Structured logs and auditability.
NFR-008: Recoverable backups.
NFR-009: Decimal monetary precision.
NFR-010: Provisional 99.9% managed SaaS monthly availability.
NFR-011: Provisional p95 under 500 ms for selected read endpoints at defined workload.
NFR-012: Horizontal scale and workload isolation.
NFR-013: Idempotent operations.
NFR-014: Metrics and distributed tracing.
NFR-015: WCAG 2.2 AA target.
NFR-016: Localization and timezone handling.
NFR-017: Provisional RPO 1 hour for qualifying tiers.
NFR-018: Provisional RTO 4 hours for qualifying tiers.
NFR-019: Retention and privacy governance.
NFR-020: Enterprise identity and deployment controls.
Performance and recovery targets are provisional engineering objectives, not contractual guarantees. Production readiness requires measured load testing, scoped security assessment and successful restoration drills.
25. Feature parity framework
Compare Infovish capabilities with the applicable versions and editions of Odoo and Flectra. Do not imply competitor availability without documented verification.
Capability
Infovish priority
Odoo
Flectra
CRM
P0
Compare applicable edition
Compare applicable edition
Sales
P0
Compare applicable edition
Compare applicable edition
Accounting
P1
Compare applicable edition
Compare applicable edition
Inventory
P1
Compare applicable edition
Compare applicable edition
Purchase
P1
Compare applicable edition
Compare applicable edition
Manufacturing
P2
Compare applicable edition
Compare applicable edition
Employees/Payroll
P1–P2
Compare applicable edition
Compare applicable edition
Projects
P1
Compare applicable edition
Compare applicable edition
POS
P2
Compare applicable edition
Compare applicable edition
E-commerce
P2
Compare applicable edition
Compare applicable edition
Marketing
P2
Compare applicable edition
Compare applicable edition
Helpdesk
P1
Compare applicable edition
Compare applicable edition
Documents
P1
Compare applicable edition
Compare applicable edition
Subscriptions
P2
Compare applicable edition
Compare applicable edition
Rental/Fleet
P2–P3
Compare applicable edition
Compare applicable edition
Studio
P3
Compare applicable edition
Compare applicable edition
Multi-company
P0
Compare applicable edition
Compare applicable edition
AI
P2
Compare applicable edition
Compare applicable edition
WhatsApp
P2
Compare applicable edition
Compare applicable edition
Cloud
P0
Compare applicable edition
Compare applicable edition
Parity register fields: feature_id, reference_product, reference_version, reference_edition, reference_module, reference_capability, reference_documentation, infovish_requirement_ids, implementation_status, test_evidence, known_gaps, license_obligations and review_date. Levels L0 Not Assessed through L5 Production Validated.
26. Module dependencies and integration contracts
Foundation (tenancy, IAM, organization, audit, module registry) precedes contacts/catalog, CRM, documents and event services; these support sales, finance, inventory, procurement, HR and projects; downstream capabilities include manufacturing, POS, commerce, subscriptions, helpdesk, logistics and industry packs. AI, BI, communications and automation span domains through governed contracts.
DEP-001: No direct queries against another module’s private tables.
DEP-002: Contract-driven cross-module interactions.
DEP-003: Idempotent event consumers.
DEP-004: Explicit transactional boundaries.
DEP-005: Compatible schema and contract migrations.
DEP-006: Permission-scoped AI tool execution.
DEP-007: Versioned integration events.
DEP-008: Observability across integration boundaries.
27. Codex implementation milestones
Milestone
Release
Scope
Exit gate
M00
R0
Architecture validation, PRD traceability, ADRs, domain/security review
Architecture decisions and risks documented
M01
R0
Monorepo, Laravel/React/TypeScript/PostgreSQL, Docker, CI, AGENTS.md
Reproducible build and tests
M02
R0
Tenant isolation, IAM, organization, audit
Tenant/security tests pass
M03
R0
Module framework, shared services, catalog and contacts
Module contracts verified
M04
R1
CRM
Lead lifecycle tests pass
M05
R1
Sales and first commercial release
Quote-to-order tests pass
M06
R2
Accounting
Journal and reconciliation invariants pass
M07
R2
Inventory and procurement
Stock and purchasing consistency pass
M08
R3
HR, projects, helpdesk, portals, documents
Cross-module workflows pass
M09
R3
Email, WhatsApp, inbox and automation
Webhook and retry tests pass
M10
R4
AI and document intelligence
AI permission and evaluation tests pass
M11
R4
Manufacturing, POS, commerce and subscriptions/rental
Transaction and integration tests pass
M12
R5
Studio and industry extension platform
Upgrade compatibility verified
M13
R5
Enterprise and cloud scale
Recovery, load and security gates pass
28. Release roadmap
R0: platform foundation
R1: CRM and Sales commercial release
R2: Finance, Inventory and Procurement
R3: HR, Projects, Helpdesk, Portals, Communications and Workflow
R4: AI, Manufacturing, POS, E-commerce and Subscriptions
R5: Studio, Industry Extensions and Enterprise capabilities
29. Codex execution contract and definition of done
For each bounded task: read approved specs; inspect repository; map requirement IDs; propose a scoped plan; implement; run relevant tests; document migrations, security and API impacts; update traceability; report observed results; stop at the approved milestone. Never fabricate test outcomes or bypass authorization.
Definition of Done: validated behavior, permissions, tenant scoping, migration safety, tests, API/event contracts, auditability where relevant, no unresolved critical findings and traceability to requirements.
30. End-to-end validation scenarios
E2E-001: Tenant provisioning.
E2E-002: Cross-tenant access denial.
E2E-003: CRM lead lifecycle.
E2E-004: Quotation conversion.
E2E-005: Idempotent order creation.
E2E-006: Balanced journal posting.
E2E-007: Period lock enforcement.
E2E-008: Stock concurrency and reservations.
E2E-009: Partial purchase receipt.
E2E-010: Payment reconciliation.
E2E-011: Leave approval.
E2E-012: External portal scoping.
E2E-013: WhatsApp webhook replay.
E2E-014: Workflow idempotency.
E2E-015: AI tool authorization.
E2E-016: AI prompt-injection handling.
E2E-017: Backup restoration.
E2E-018: Module upgrade compatibility.
E2E-019: Financial report reconciliation.
E2E-020: Tenant suspension and recovery.
31. Risks and open decisions
RSK-001: Scope expansion; mitigate through phased delivery, ADRs, validation and release gates.
RSK-002: Finance localization and correctness; mitigate through phased delivery, ADRs, validation and release gates.
RSK-003: Tenant data leakage; mitigate through phased delivery, ADRs, validation and release gates.
RSK-004: Codex-generated inconsistency; mitigate through phased delivery, ADRs, validation and release gates.
RSK-005: Reference-product licensing; mitigate through phased delivery, ADRs, validation and release gates.
RSK-006: WhatsApp provider policy changes; mitigate through phased delivery, ADRs, validation and release gates.
RSK-007: AI accuracy and privacy; mitigate through phased delivery, ADRs, validation and release gates.
RSK-008: Studio upgrade compatibility; mitigate through phased delivery, ADRs, validation and release gates.
RSK-009: Cloud infrastructure cost; mitigate through phased delivery, ADRs, validation and release gates.
RSK-010: Sri Lankan tax and payroll localization; mitigate through phased delivery, ADRs, validation and release gates.
RSK-011: Enterprise load assumptions; mitigate through phased delivery, ADRs, validation and release gates.
RSK-012: Customer migration complexity; mitigate through phased delivery, ADRs, validation and release gates.
Open decisions: tenancy topology validation; licensing; Sri Lankan accounting and payroll requirements; payment gateways; hosting provider; offline mobile scope; enterprise commitments.
32. Documentation and readiness
Maintain PRD.md, SAD.md, REQUIREMENTS_TRACEABILITY.md, FEATURE_PARITY_MATRIX.md, MODULE_CATALOG.md, DATABASE_DESIGN.md, API_SPECIFICATION.yaml, SECURITY_MODEL.md, TEST_STRATEGY.md, DEPLOYMENT_GUIDE.md, AGENTS.md, CHANGELOG.md and architecture decisions.
This document is a comprehensive product-level implementation baseline. Detailed domain models, field-level schemas, API specifications and localization policies must be approved before implementing affected modules. The next companion artifact is the System Architecture Document (SAD v1.0).
