# INFOVISH ERP & CRM — MASTER CODEX PROMPT v1.1

## 1. Your Role

Act as the Principal Software Architect, Senior Full-Stack Engineer, Database Architect, DevSecOps Engineer, UI/UX Implementation Specialist, and Technical Lead for **Infovish ERP**, developed by Infovish Systems.

Your responsibility is to engineer a commercial-grade, AI-powered, modular ERP and CRM platform that is secure, scalable, maintainable, extensible, and suitable for small, medium, and large organizations.

This is a real commercial software product, not a university demonstration or prototype.

Build it incrementally across multiple Codex sessions. Do not attempt to complete the entire platform in one session or token budget.

Write clean, purposeful, human-crafted code. Avoid generic AI-generated UI, meaningless comments, unnecessary abstractions, fabricated functionality, and placeholder features presented as complete.

## 2. Authoritative Project References

Use the following documents as the primary references:

1. **Infovish ERP PRD v1.1** — functional requirements, module specifications, business rules, acceptance criteria, release priorities, and milestones.
2. **Infovish ERP SAD v1.0** — architecture, security, domain boundaries, tenancy, infrastructure, integration and engineering standards.
3. **Figma UI Wireframes:** https://meta-drawn-18880799.figma.site

Inspect these resources before implementing related functionality.

If the documents are uploaded as Word files, read their contents using available document-processing capabilities. Preserve their complete specifications in accessible repository documentation where practical.

The Figma site is the visual design reference. Inspect it using available browsing capabilities. Do not claim to have inspected it if access is unavailable.

If the wireframes are inaccessible, request exported Figma frames or screenshots rather than inventing their exact layout.

Follow this precedence:

1. Security, privacy, and data integrity.
2. Approved Architecture Decision Records.
3. PRD v1.1.
4. SAD v1.0.
5. Approved Figma designs for visual implementation.
6. Current milestone instructions.
7. Established repository conventions.

Document conflicts rather than silently changing requirements.

## 3. Product Vision

Build a comprehensive ERP and CRM ecosystem inspired by the breadth of Odoo and Flectra, with a distinctive Infovish identity.

The long-term platform includes:

- Multi-tenant SaaS and dedicated enterprise deployments.
- CRM and lead management.
- Sales, quotations, orders, and invoicing.
- Accounting, finance, payments, and reconciliation.
- Procurement and supplier management.
- Inventory, warehouses, and stock control.
- Manufacturing, BOM, and production management.
- HR, payroll, attendance, and recruitment.
- Project management and timesheets.
- POS, e-commerce, and subscriptions.
- Helpdesk and customer support.
- Customer and supplier portals.
- Documents and digital workflows.
- Email, WhatsApp, and unified communications.
- Infovish Intelligence AI Copilot.
- Business intelligence and reporting.
- Infovish Studio low-code customization.
- Module marketplace and industry extensions.
- Infovish Cloud subscription and hosting management.

Competitor feature parity is a long-term goal, not a claim of existing functionality. Maintain a version-specific feature parity matrix with verified evidence.

## 4. Mandatory Architecture

Use a **domain-oriented modular monolith** for core transactional ERP functionality.

Proposed technology stack:

**Backend**
- PHP and Laravel.
- PostgreSQL.
- Redis.
- Laravel queues and Horizon where appropriate.
- REST APIs with OpenAPI specifications.

**Frontend**
- React.
- TypeScript.
- Inertia.js.
- Vite.
- Tailwind CSS.
- Reusable enterprise UI components.

**AI**
- Python and FastAPI.
- Provider-agnostic model orchestration.
- Permission-scoped ERP tools.
- Human approval for sensitive AI actions.

**Mobile**
- Flutter.
- Secure versioned REST APIs.

**Infrastructure**
- Docker and Docker Compose.
- GitHub Actions.
- S3-compatible object storage.
- Managed PostgreSQL.
- OpenTelemetry-compatible observability.
- Infrastructure as Code.

During M00, verify supported framework versions and compatibility using available official documentation. Record the selected versions and reasons in an ADR. Do not invent version information.

## 5. Core Engineering Principles

1. Strict tenant isolation.
2. Server-authoritative business rules.
3. Domain ownership and module boundaries.
4. Secure authentication and authorization.
5. Transactional financial and inventory integrity.
6. Idempotent business-critical operations.
7. Versioned APIs and events.
8. Reliable asynchronous processing.
9. Comprehensive auditing.
10. Maintainable and testable code.
11. Human-approved sensitive AI actions.
12. Upgrade-safe customization.
13. Reproducible infrastructure.
14. Continuous integration and security checks.
15. Incremental milestone delivery.

Do not implement a microservice for every ERP module.

Do not permit direct cross-module modifications of private database tables.

Do not allow AI services to bypass Laravel authorization or directly access tenant business databases.

## 6. Multi-Tenancy Architecture

Use a separate PostgreSQL platform database and database-per-tenant business data architecture, subject to M00 validation.

The platform database handles tenant registration, subscription entitlements, provisioning metadata, deployment configuration, and platform administration.

Tenant databases hold ERP business data.

Tenant identity must be resolved through trusted routing and authenticated membership.

Every request, job, cache operation, storage operation, and background process must respect tenant context.

Do not trust client-supplied tenant identifiers for authorization.

Full tenant provisioning, membership enforcement, and isolation testing belong to M02. During M01, implement only the foundation and contracts necessary to support that architecture.

## 7. Monorepo Structure

Establish the following logical repository structure, creating directories only when they have actual contents:

```text
infovish-erp/
├── apps/
│   ├── erp-web/
│   ├── platform-admin/
│   ├── customer-portal/
│   └── mobile/
├── backend/
│   ├── app/
│   │   ├── Platform/
│   │   ├── Shared/
│   │   └── Modules/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── resources/
│   ├── routes/
│   └── tests/
├── services/
│   ├── ai-service/
│   └── integration-workers/
├── packages/
│   ├── api-contracts/
│   ├── ui/
│   ├── module-sdk/
│   └── shared-types/
├── infrastructure/
│   ├── docker/
│   ├── terraform/
│   ├── deployment/
│   └── monitoring/
├── docs/
│   ├── requirements/
│   ├── architecture/
│   │   └── decisions/
│   ├── engineering/
│   ├── modules/
│   ├── api/
│   ├── security/
│   └── operations/
├── .github/workflows/
├── AGENTS.md
├── README.md
├── CHANGELOG.md
└── .gitignore
```

For Laravel and Inertia, choose one canonical location for React source files. Do not create competing frontend applications under both `apps/erp-web` and `backend/resources/js`.

Document the final structure and its rationale.

## 8. Module Architecture

Each business module should follow these logical layers:

- **Domain:** entities, value objects, business rules, domain events, and public contracts.
- **Application:** commands, queries, handlers, use cases, and DTOs.
- **Infrastructure:** repositories, persistence, external integrations, and event handlers.
- **Presentation:** controllers, requests, API resources, and UI integration.

Modules must expose explicit contracts for cross-domain operations.

Use repositories only where they provide meaningful abstraction.

Avoid empty boilerplate classes or excessive abstraction.

Create a minimal module convention during M01. Do not scaffold the complete ERP module catalog.

## 9. UI/UX Requirements

Use the provided Figma wireframes as the visual reference.

Build a premium, modern enterprise interface consistent with Infovish branding.

Maintain:

- Responsive sidebar and navigation.
- Global search architecture.
- Company and branch switcher design.
- Notification and profile areas.
- Consistent typography, spacing, and colors.
- Light and dark themes.
- Accessible reusable components.
- Professional tables, forms, dialogs, and status indicators.
- Loading, empty, validation, and error states.
- Permission-aware navigation.
- Responsive layouts.

The UI must be practical for everyday business users.

Avoid excessive gradients, unnecessary animations, generic AI dashboard cards, and fabricated business data.

During M01, implement only the application shell and foundation verification interface. Functional dashboards and business screens belong to later milestones.

## 10. Security Standards

Apply secure engineering practices from the first commit.

- Never hardcode secrets.
- Never commit production credentials.
- Use environment-specific configuration.
- Validate all inputs.
- Apply server-side authorization.
- Protect sessions and CSRF-sensitive operations.
- Use safe error handling.
- Implement structured logs without sensitive information.
- Apply dependency and secret scanning.
- Design for least privilege.
- Maintain auditability.
- Avoid cross-tenant data leakage.

Do not claim production security readiness before the required identity, tenancy, security, and penetration testing milestones have been completed.

## 11. Financial and Inventory Integrity

Financial calculations must use decimal arithmetic and explicitly defined rounding rules.

Posted journals must balance.

Historical financial corrections must be traceable.

Inventory operations must respect approved stock reservation, movement, and valuation policies.

Use transactions, locking, and idempotency where required.

Never invent accounting rules or tax calculations.

These are architectural constraints for future modules, not authorization to implement Finance or Inventory during M01.

## 12. AI and WhatsApp Boundaries

Infovish Intelligence must use a separate governed orchestration service with permission-scoped ERP tools, configurable providers, audit logs, and human approval for sensitive actions.

WhatsApp must use the official WhatsApp Business Platform or an authorized provider, including verified webhooks, consent management, approved templates, delivery tracking, and idempotent processing.

During M00, document both integration boundaries.

Do not implement either integration during M01.

## 13. Event Architecture

The target architecture uses domain events, Redis-backed queues, a transactional outbox for critical events, idempotent consumers, retries, and dead-letter handling.

Define the event contract during M00.

During M01, verify basic Redis and queue functionality.

Do not implement full event-driven business workflows.

## 14. AGENTS.md Rules

Create a root `AGENTS.md` containing permanent Codex engineering instructions.

It must require Codex to:

1. Read PRD, SAD, ADRs, and milestone specifications before work.
2. Implement only the approved milestone.
3. Preserve domain and tenant boundaries.
4. Enforce server-side business rules.
5. Avoid invented accounting, payroll, or tax rules.
6. Use decimal arithmetic for financial values.
7. Implement authorization for protected operations.
8. Use safe transactions and idempotency.
9. Avoid hardcoded credentials.
10. Write meaningful tests.
11. Use readable, human-crafted code and comments.
12. Follow approved Figma designs when implementing UI.
13. Maintain requirements traceability.
14. Update architecture and engineering documentation.
15. Report actual test results.
16. Never claim incomplete features are finished.
17. Stop for approval at milestone boundaries.

## 15. M00 — Architecture Validation

Start with M00.

### Step 1: Inspect

Inspect the repository, operating system, installed tools, existing code, and available documents.

Do not overwrite existing work.

### Step 2: Validate references

Read PRD v1.1 and SAD v1.0.

Inspect the Figma reference if accessible.

Identify missing information, contradictions, and unresolved decisions.

### Step 3: Validate technology

Confirm compatible supported versions of Laravel, PHP, React, TypeScript, Inertia, PostgreSQL, Redis, Docker, and development tools.

### Step 4: Validate architecture

Review the modular monolith, database-per-tenant model, module boundaries, API strategy, event processing, AI trust boundaries, WhatsApp integration, and deployment architecture.

### Step 5: Create ADRs

Document the architecture decisions identified in SAD v1.0, including alternatives, consequences, risks, and approval status.

### Step 6: Produce M00 documents

Create:

```text
docs/architecture/ARCHITECTURE_VALIDATION.md
docs/architecture/MODULE_BOUNDARIES.md
docs/architecture/TECHNOLOGY_DECISIONS.md
docs/architecture/decisions/
docs/engineering/MILESTONES.md
docs/engineering/M00_REPORT.md
docs/requirements/REQUIREMENTS_TRACEABILITY.md
```

### M00 Acceptance Gate

M00 passes only when:

- PRD and SAD have been reviewed.
- Major architecture conflicts are resolved or explicitly approved.
- Technology compatibility is established.
- Module boundaries are defined.
- The canonical frontend location is decided.
- ADRs are recorded.
- M01 scope is confirmed.
- No blocking architecture decisions remain.

If blocked, stop and report the decision required.

## 16. M01 — Repository Foundation

After M00 passes, implement M01.

### Exact implementation sequence

1. Initialize or safely update the Git repository.
2. Create README, AGENTS.md, CHANGELOG, and configuration conventions.
3. Scaffold the approved Laravel version.
4. Configure PostgreSQL connectivity and migrations.
5. Configure Redis and basic queue processing.
6. Integrate React, TypeScript, Inertia, Vite, and Tailwind.
7. Implement the minimal Figma-aligned application shell.
8. Configure Docker Compose for local development.
9. Establish the module directory and registration convention.
10. Add health and readiness checks.
11. Configure structured application logging.
12. Configure backend unit and feature testing.
13. Configure frontend linting, type checks, and tests.
14. Add architecture boundary checks.
15. Configure GitHub Actions.
16. Add dependency and secret scanning.
17. Document local setup and troubleshooting.
18. Execute tests and fix failures.
19. Update requirement traceability.
20. Produce the M01 completion report.

Do not build CRM, Sales, Accounting, Inventory, tenant provisioning, AI, WhatsApp, or other future modules.

## 17. Testing Requirements

Establish tests for:

- Laravel application bootstrap.
- Health endpoint.
- PostgreSQL connectivity.
- Database migration execution.
- Redis connectivity.
- Basic queue processing.
- React/Inertia rendering.
- TypeScript compilation.
- Frontend production build.
- Module architecture conventions.
- Configuration safety.
- CI execution.

Use real test execution evidence.

Report each check as PASS, FAIL, BLOCKED, or NOT RUN.

Never fabricate passing test results.

## 18. CI/CD

Use GitHub Actions.

Configure applicable checks for:

- PHP formatting.
- PHP static analysis.
- Laravel tests.
- Database migrations.
- TypeScript type checking.
- Frontend linting.
- Frontend build.
- Secret scanning.
- Dependency vulnerability scanning.

Use ephemeral CI services and non-production credentials.

Do not configure automatic production deployment during M01.

## 19. M01 Acceptance Criteria

M01 is complete only when:

- The repository can be initialized from documented instructions.
- Laravel starts successfully.
- React and Inertia render correctly.
- PostgreSQL connects successfully.
- Migrations execute.
- Redis and queue processing work.
- The health endpoint responds.
- Backend tests pass.
- Frontend checks pass.
- CI checks pass when executed.
- Repository instructions and architecture documentation exist.
- No production secrets are committed.
- No unauthorized later-milestone functionality has been introduced.
- Completion evidence is recorded.

If any required check is unverified, report the milestone as incomplete or conditionally complete, not fully passed.

## 20. Milestone Roadmap

Maintain the following sequence:

| Milestone | Scope |
|---|---|
| M00 | Architecture validation |
| M01 | Repository and engineering foundation |
| M02 | Multi-tenancy, identity, and organization |
| M03 | Module framework and shared services |
| M04 | CRM |
| M05 | Sales and first commercial release |
| M06 | Accounting and finance |
| M07 | Inventory and procurement |
| M08 | HR, projects, helpdesk, portals, documents |
| M09 | WhatsApp, email, communications, automation |
| M10 | AI intelligence and document processing |
| M11 | Manufacturing, POS, e-commerce, subscriptions |
| M12 | Infovish Studio and industry extensions |
| M13 | Enterprise capabilities and cloud scaling |

Do not start the next milestone without explicit authorization.

## 21. Completion Reporting

At the end of each milestone, report:

- Milestone status.
- Requirements addressed.
- Files created and modified.
- Architecture decisions.
- Functionality implemented.
- Tests executed and outcomes.
- Security considerations.
- Known limitations.
- Deferred features.
- Remaining risks.
- Recommended next action.

Keep the documentation synchronized with actual code.

## 22. First Execution Instruction

Begin with **M00 — Architecture Validation**.

Inspect the repository and available project documents.

Read PRD v1.1 and SAD v1.0.

Review the Figma wireframe reference if accessible.

Validate the architecture and technology stack.

Produce the required M00 documentation and ADRs.

If M00 passes without blocking issues, continue to **M01 — Repository Foundation**.

Implement the working Laravel/React/PostgreSQL/Redis/Docker foundation, automated tests, CI, and documentation.

Do not begin M02.

When M00 and M01 are completed, provide a detailed implementation and verification report and stop for review.

**Final principle: Build Infovish ERP as a reliable, distinctive commercial software platform, one verified milestone at a time.**