INFOVISH ERP & CRM
SYSTEM ARCHITECTURE DOCUMENT
SAD v1.0  |  INF-ERP-SAD-001
Prepared for: Infovish Systems
Reference: Product Requirements Document v1.1
Document status: Proposed engineering baseline
Date: 29 October 2026
UI wireframe: https://meta-drawn-18880799.figma.site
This document defines the proposed target architecture and phased implementation controls. No production readiness or implementation completion is implied.
Document control
Field
Value
Document ID
INF-ERP-SAD-001
Version
1.0
Parent specification
Infovish ERP PRD v1.1
Architecture
Domain-oriented modular monolith with isolated services
Implementation
Incremental ChatGPT Codex milestones M00–M13
Figma wireframes
https://meta-drawn-18880799.figma.site
Approval
Pending M00 architecture review
Wireframe reference
Figma UI wireframe prototype supplied by the product owner: https://meta-drawn-18880799.figma.site. Treat this as a design reference, not an authoritative source of implemented behavior. Validate accessibility, responsive states, navigation, permissions and consistency against the PRD before coding screens.
1. Executive summary
Infovish ERP is a modular, multi-tenant ERP and CRM platform for organizations ranging from small businesses to enterprise deployments. Its core transactional services will use Laravel and PostgreSQL within a modular monolith; separately deployed services will support governed AI and selected integrations. React/TypeScript/Inertia serves the web interface; Flutter is planned for mobile.
Concern
Architecture baseline
Backend
Laravel / PHP modular monolith
Frontend
React + TypeScript + Inertia + Tailwind
Data
PostgreSQL platform database + database per tenant
Async
Redis queues, transactional outbox and idempotent consumers
AI
FastAPI with permission-scoped ERP tool gateway
Messaging
Official WhatsApp Business Platform Cloud API
Infrastructure
Docker, managed data services, IaC, CI/CD
APIs
Versioned REST, OpenAPI, authenticated webhooks
1.1 Architecture principles
Enforce all financial, inventory and authorization invariants server-side.
Each module owns its data and publishes explicit public contracts.
Never authorize a tenant solely from untrusted client-provided identifiers.
Treat external messages, AI retrieval and webhooks as untrusted inputs.
Preserve transactional consistency; design async delivery as at-least-once.
Favor reproducible infrastructure, observable behavior and testable boundaries.
Build only the approved milestone and document deviations through ADRs.
2. C4 system architecture
Figure 1. C4 Level 1 — people, ERP system and external dependencies.
Actors include tenant administrators, employees, managers, customers, suppliers, partner developers and Infovish operators. Trust boundaries separate each tenant, the platform control plane and external providers.
Figure 2. C4 Level 2 — deployable containers and state services.
Container
Primary responsibility
ERP Web
Authenticated user experience through Inertia
Laravel ERP
Business transactions, authorization and public REST APIs
Control plane
Tenant lifecycle, subscriptions and provisioning
FastAPI AI
Prompt/model routing, governed tools, evaluations
Integration workers
Provider callbacks, outbound delivery and retries
PostgreSQL
Authoritative platform and tenant business data
Redis
Queues, cache and coordination
Object storage
Authorized document and attachment storage
Figure 3. C4 Level 3 — application component layering.
2.4 C4 Level 4 — example implementation path
CreateLeadController -> CreateLeadCommand -> CreateLeadHandler
 -> Lead domain model -> LeadRepository -> PostgreSQL
Handlers resolve tenant and actor context, authorize, validate, persist and record events. Where reliable publication is required, the outbox record commits in the same database transaction as business state.
3. Domain architecture and module boundaries
Bounded context
Ownership
Platform
Tenants, plans, provisioning, entitlements
Identity
Authentication, roles, membership, permissions
Organization
Companies, branches, departments, fiscal configuration
Parties
Customers, suppliers, contacts
Catalog
Products, variants, prices
CRM
Leads, opportunities, activities
Sales
Quotations, orders, fulfillment commitments
Finance
Journals, invoices, payments, reconciliation
Procurement
Requisitions, RFQs, purchase orders
Inventory
Stock ledger, reservations, locations
Manufacturing
BOM, work orders, production
HR
Employees, leave, attendance, payroll
Projects
Tasks, timesheets, field services
Communications
Email, WhatsApp, inbox, conversations
Documents
Files, versions, metadata, approvals
Automation
Workflow triggers, approvals, execution
Analytics
Governed reports and read models
AI
AI policies, orchestration requests, approvals
3.1 Module integration rules
A module may not directly update another module’s private tables.
Cross-module synchronous operations use public application contracts; async operations use versioned events.
No module may bypass the central authorization or tenant context mechanisms.
Publish stable identifiers and DTOs rather than leaking Eloquent internals.
Use an orchestrated process manager for long-running workflows with explicit failure and compensation states.
Define dependency manifests and validate installation/upgrade compatibility.
3.2 Representative workflows
Sales-to-invoice: confirmation creates an authorized order and event; downstream fulfillment and invoicing validate their own business rules and deduplicate retries. Procurement-to-stock: authorized receipt drives an idempotent inventory movement; finance applies approved valuation rules. Manufacturing: reserve inputs, consume, record output and costs, and support partial completion.
4. Tenant and database architecture
Figure 4. Platform database, tenant resolution and data isolation.
4.1 Tenant request lifecycle
Resolve tenant from trusted host/routing metadata.
Authenticate the principal and validate tenant membership.
Check tenant lifecycle, company scope and entitlements.
Bind an isolated tenant database connection.
Execute authorized application operations.
Clear tenant context on request completion and every queue job.
4.2 Data integrity
Use UUID-based public identifiers and separate company-scoped document numbers. Use PostgreSQL NUMERIC for currency and stock quantities, explicit rounding and unit conversion policies, foreign keys, uniqueness constraints, and appropriate locking. Posted journal and stock histories must be append-only or preserve equivalent immutable audit trails; corrections use reversals or adjustments.
4.3 Migration and backup strategy
Use controlled tenant-by-tenant migrations with version tracking, locks, failure reporting and expand/contract compatibility. Separate large backfills from schema releases. Backups must cover tenant databases and object storage consistently; restore tests must prove recovery, not merely confirm backup jobs ran.
5. Laravel backend design
backend/app/
  Platform/{Tenancy,Identity,Subscriptions,Provisioning,ModuleRegistry}/
  Shared/{Auth,Audit,Events,Money,Contracts}/
  Modules/{Parties,Catalog,CRM,Sales,Finance,Procurement,Inventory,...}/
backend/{routes,database,tests,config}/
Each module follows Domain / Application / Infrastructure / Presentation layers with dedicated migrations, tests, module manifest and README as justified by complexity. Controllers stay thin; business logic belongs to domain/application services. Eloquent is a persistence tool, not an unrestricted cross-module interface.
5.1 Backend requirements
ID
Architectural control
ARC-BE-001
Thin controllers; use-case services
ARC-BE-002
Server-enforced business invariants
ARC-BE-003
Verified tenant and actor context
ARC-BE-004
No undocumented private-table coupling
ARC-BE-005
Explicit financial and stock transactions
ARC-BE-006
Retryable and idempotent background jobs
ARC-BE-007
Versioned REST and response contracts
ARC-BE-008
Reliable critical event publication
ARC-BE-009
Validated module dependencies
ARC-BE-010
Structured redacted error logging
5.2 API conventions
/api/v1/me
/api/v1/companies
/api/v1/crm/leads
/api/v1/sales/orders
/api/v1/finance/invoices
/api/v1/inventory/stock-movements
/api/v1/communications/conversations
Standardize pagination, filters, error envelopes, correlation IDs, rate limits, API authorization and idempotency keys for repeated write operations. Maintain OpenAPI contracts and compatibility tests.
6. React / TypeScript / Inertia architecture
The ERP web app uses React, TypeScript, Inertia.js, Vite and Tailwind. Select one canonical frontend source location during M00; do not create a competing React app alongside Laravel resources. Group frontend features by bounded context; share accessible UI primitives, tables, forms, charts, feedback and navigation.
erp-web/src/{app,components,features,hooks,lib,services,types,styles}/
features/{crm,sales,finance,inventory,...}/
UI requirements include a responsive shell, permission-aware navigation, company switcher, reusable list/form/Kanban/calendar views, global search, keyboard accessibility, light/dark themes, localization-ready formatting, and clear loading/empty/error states. UI permission hiding never replaces server authorization.
Figma wireframes: https://meta-drawn-18880799.figma.site. Validate all design assumptions against actual business workflows and the requirements before implementation.
7. AI orchestration
Figure 5. AI execution trust boundary and tool governance.
AI requests originate from authenticated ERP contexts. Laravel verifies tenant, actor, permissions, entitlements and budgets; FastAPI orchestrates approved models, retrieval and structured outputs. Every read/write tool is reauthorized by the ERP gateway. High-impact actions require explicit approval. No direct AI-to-tenant-database credentials are permitted.
Risk
Required mitigation
Prompt injection
Untrusted retrieval; independent tool authorization
Cross-tenant data exposure
Tenant-scoped retrieval and API policies
Sensitive data leakage
Minimization, approved provider handling
Unauthorized writes
Approval and deterministic domain checks
Hallucinated metrics
Traceable structured calculations
Model outage/cost
Circuit breakers, quotas, fallback
8. Official WhatsApp Business integration
Figure 6. Official provider webhook and communications flow.
Use Meta WhatsApp Business Platform Cloud API or an authorized provider, with current supported API versions and policy checks at implementation. Validate webhook signatures and map registered business number identifiers to authorized tenants. Deduplicate inbound events; queue processing; maintain conversation/message/template/consent/delivery records. For outbound messages enforce opt-in, approved templates and applicable messaging windows.
9. Event-driven integration
Figure 7. Transactional outbox, queue and idempotent consumer.
Delivery semantics are at-least-once. Outbox rows commit atomically with business transactions; a dispatcher publishes them to Redis-backed queues. Consumers validate schema/version/tenant and record processed event IDs. Retry transient errors with bounded exponential backoff; classify permanent failures; expose dead-letter replay and monitoring.
{
  "event_id": "<uuid>",
  "event_type": "sales.order.confirmed",
  "event_version": 1,
  "tenant_id": "<uuid>",
  "aggregate_id": "<uuid>",
  "correlation_id": "<id>",
  "occurred_at": "<UTC timestamp>",
  "payload": { "order_id": "<uuid>" }
}
10. Security architecture
TLS, trusted proxy configuration, WAF and rate limits at the edge.
Secure session and CSRF protection for Inertia; scoped tokens/OAuth for mobile and integrations.
Role, tenant, company, record and field-level authorization enforced server-side.
MFA for privileged users; OIDC SSO for approved enterprise deployments.
Secret management and rotation; no credentials in logs, source or AI prompts.
Encrypted backups and authorized object access; retention and privacy workflows.
Structured audit logs for privileged actions, financial changes and integrations.
Security testing for tenant escape, IDOR, CSRF, injection, webhook replay and AI misuse.
ID
Security acceptance test
SEC-001
Cross-tenant read/write isolation
SEC-002
Privilege escalation prevention
SEC-003
IDOR prevention
SEC-004
Session revocation
SEC-005
CSRF and injection defense
SEC-006
Malicious file handling
SEC-007
Webhook authenticity/replay
SEC-008
Secrets and dependency scans
SEC-009
AI prompt-injection/tool misuse
SEC-010
Backup and sensitive-data protection
SEC-011
Rate limiting
SEC-012
Audit integrity
11. Infrastructure and deployment
Figure 8. Proposed production deployment topology.
Environment
Purpose
Local
Developer Docker stack and synthetic fixtures
CI
Ephemeral test services
Development
Shared integration with synthetic data
Staging
Production-like validation with sanitized data
Production
Protected customer workloads
Use containerized services, managed PostgreSQL, Redis, S3-compatible object storage, dedicated secrets and observability. Provision infrastructure with Terraform or approved IaC. Kubernetes is deferred until operational scale justifies it. Use independent queue classes for critical transactions versus reporting, marketing and AI jobs.
11.1 Proposed operational targets
Measure
Initial target / qualification
Availability
99.9% monthly managed service target, not yet guaranteed
RPO
1 hour for qualifying managed tiers
RTO
4 hours for qualifying managed tiers
Performance
Representative p95 reads under 500 ms only after workload definition and tests
Recovery
Scheduled verified restore drills
Scaling
Measured workload-driven capacity planning
12. CI/CD, testing and operations
CI should perform PHP formatting/static analysis/tests, TypeScript lint/typecheck/build, Python checks when present, migrations, architecture boundary tests, OpenAPI validation, secret/dependency scanning, integration and critical E2E tests. Protected branches and reviewed changes feed staged deployments. Schema changes must use compatibility-aware migration strategies; container rollback does not imply automatic database rollback.
Capture structured logs, metrics and distributed traces across Laravel, queues, FastAPI and providers. Monitor request latency, database locks, queue depth, outbox lag, AI cost, webhook errors, backup success and tenant provisioning/migration status. Maintain runbooks for outages, tenant restores, failed migrations and security incidents.
13. Mobile and extension architecture
Flutter mobile uses feature-based modules, secure API access and explicit offline workflow design. Offline writes require encrypted local storage where applicable, reconciliation and conflict policies; financial and inventory-critical writes cannot be assumed safe offline.
Infovish Studio uses versioned metadata for fields, forms, views and workflows. Extensions declare dependencies, capabilities, permissions and compatible platform versions. Never execute arbitrary untrusted customer PHP code inside the shared SaaS application.
14. Architecture decision register
ADR
Proposed decision
Status
ADR-001
Modular monolith
Proposed — M00 review
ADR-002
Laravel transactional backend
Proposed — M00 review
ADR-003
React/TypeScript/Inertia frontend
Proposed — M00 review
ADR-004
PostgreSQL database per tenant
Proposed — M00 review
ADR-005
Separate platform control plane
Proposed — M00 review
ADR-006
Redis queues and transactional outbox
Proposed — M00 review
ADR-007
FastAPI AI service
Proposed — M00 review
ADR-008
Official WhatsApp Business APIs
Proposed — M00 review
ADR-009
REST and OpenAPI
Proposed — M00 review
ADR-010
Flutter mobile
Proposed — M00 review
ADR-011
Docker managed deployment
Proposed — M00 review
ADR-012
Metadata-driven customization
Proposed — M00 review
ADR-013
Governed AI tools
Proposed — M00 review
ADR-014
Domain ownership boundaries
Proposed — M00 review
ADR-015
Managed database and object storage
Proposed — M00 review
ADR-016
Defer Kubernetes
Proposed — M00 review
ADR-017
Automated security and architecture tests
Proposed — M00 review
ADR-018
Industry extension isolation
Proposed — M00 review
Every ADR must document context, alternatives, rationale, consequences, security/operational implications, migration approach and related requirements. Do not silently mark disputed decisions accepted.
15. Codex repository standards
infovish-erp/
  apps/{erp-web,platform-admin,customer-portal,mobile}/
  backend/{app,bootstrap,config,database,routes,tests}/
  services/{ai-service,integration-workers}/
  packages/{api-contracts,ui,module-sdk,shared-types}/
  infrastructure/{docker,terraform,deployment,monitoring}/
  docs/{requirements,architecture,engineering,modules,api,security,operations}/
  .github/workflows/
  AGENTS.md  README.md  CHANGELOG.md
The root AGENTS.md must require reading PRD/SAD/ADRs, implementing only approved milestones, respecting module boundaries, tenant authorization, financial precision, test evidence, purposeful comments, documentation updates and review gates. Do not generate empty scaffolding for all future modules.
16. Codex milestone roadmap
Milestone
Implementation scope
M00
Architecture validation and ADRs
M01
Monorepo, Laravel/React, Docker, CI
M02
Tenancy, IAM, organization, audit
M03
Module registry and shared catalog
M04
CRM
M05
Sales and first commercial release
M06
Accounting
M07
Inventory and procurement
M08
HR, projects, helpdesk, portals, documents
M09
Email, WhatsApp, inbox, automation
M10
AI and document intelligence
M11
Manufacturing, POS, commerce, subscriptions
M12
Studio and industry extensions
M13
Enterprise scale and cloud maturity
M00 must validate the architecture, select compatible tool versions, resolve blocking decisions and produce ADRs. M01 then creates a working Laravel/React/Inertia foundation with PostgreSQL, Redis, Docker, health checks, tests, CI and documented developer setup. Stop before M02 for review.
17. Architecture acceptance gates
ID
Required validation
SAD-AC-001
Reproducible repository setup
SAD-AC-002
Automated module boundary validation
SAD-AC-003
Cross-tenant isolation verification
SAD-AC-004
Approved authentication controls
SAD-AC-005
Transactional financial/inventory integrity
SAD-AC-006
Outbox delivery and replay reliability
SAD-AC-007
Webhook idempotency
SAD-AC-008
AI permission enforcement
SAD-AC-009
Module compatibility checks
SAD-AC-010
CI prevents failing deployments
SAD-AC-011
Distributed traceability
SAD-AC-012
Verified restoration
SAD-AC-013
Versioned API contracts
SAD-AC-014
Measured performance targets
SAD-AC-015
Security findings resolved
18. Open decisions and implementation dependencies
Confirm database-per-tenant topology, provisioning approach, connection pooling and dedicated enterprise hosting.
Select specific supported framework and runtime versions during M00.
Finalize Sri Lankan accounting, tax, payroll and localization rules with qualified domain reviewers.
Approve provider onboarding, payment integrations and WhatsApp credentials lifecycle.
Define exact module data models, event schemas, public APIs and ownership before implementing each module.
Specify cloud provider, capacity, monitoring, backup and recovery operations.
Define offline mobile scope, AI provider policies and enterprise commitments.
19. Document status and next action
This SAD is an implementation-oriented architectural baseline, not evidence that all subsystems are coded or production-validated. Next: complete M00 architecture review, approve blocking ADRs, then implement M01 engineering foundation through Codex. Review Figma wireframes as a visual reference at https://meta-drawn-18880799.figma.site.
