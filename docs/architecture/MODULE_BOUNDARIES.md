# Module ownership and integration boundaries

Each context owns writes and private persistence. Cross-context access uses explicit application interfaces published under `Domain/Contracts` or versioned events; no private-table writes, shared Eloquent models or raw cross-module SQL. Public contracts must be listed in the module README and manifest dependencies. Reports use permission-scoped projections rather than unrestricted joins across tenant databases.

| Context | Owns | Public interaction / milestone |
|---|---|---|
| Platform | Tenants, subscriptions, provisioning, entitlements | Trusted lifecycle and membership boundary; M02 |
| Identity | Actors, roles, memberships, sessions | Authorization decisions and scoped principal; M02 |
| Organization | Companies, branches, departments, fiscal setup | Scope and numbering contracts; M02 |
| Parties | Customer/supplier/contact identities | Stable party IDs and scoped lookup; M03 |
| Catalog | Products, variants and prices | Product definitions, never stock balances; M03 |
| CRM | Leads, opportunities, activities | Authorized conversion intent to Parties/Sales; M04 |
| Sales | Quotes, orders and fulfillment commitments | Order confirmation and invoicing requests; M05 |
| Finance | Journals, invoices, payments and reconciliation | Posting/payment contracts enforce balance/locks; M06 |
| Procurement | Requisitions, RFQs and purchase orders | Receipt intent to Inventory and invoice matching; M07 |
| Inventory | Stock ledger, reservations, locations | Atomic reserve/receive/move contracts; M07 |
| HR | Employees, attendance, leave and payroll | Approved HR workflows; M08, localization reviewed first |
| Projects | Tasks, timesheets and field work | Authorized allocations and billable time; M08 |
| Helpdesk/Portals | Tickets, queues and external self-service | Explicit external record scope; M08 |
| Documents | Files, versions, metadata and approvals | Authorized object access; M08 |
| Communications | Email, WhatsApp, consent and conversations | Verified provider events and outbound intents; M09 |
| Automation | Triggers, approval state and execution | Allowlisted authorized actions and audited retries; M09 |
| AI | Orchestration policy, requests and approvals | Reauthorized Laravel tool gateway; M10 |
| Analytics | Governed projections and report definitions | Read models with tenant/company/field scope; phased |
| Manufacturing | BOMs, work orders and production | Inventory consumption/output and approved cost contracts; M11 |
| Commerce/Subscriptions | POS sessions, storefront and recurring lifecycle | Sales/Finance contracts; M11 |
| Studio/Industry | Custom metadata and extension packages | Capability/compatibility validation; M12 |

Platform and Shared are infrastructure boundaries, not a license for business logic to migrate into global services. Shared holds only genuinely shared contracts (tenant context, event envelope, precision, observability). Use thin controllers, application use cases, domain invariants and infrastructure adapters as actual complexity demands. Repositories are optional and justified by behavior.

Registration in M01 uses an explicit allowlist in `bootstrap/providers.php`, not directory autoload activation. M03 now implements bundled manifests, explicit public DTO contracts, operator entitlements, schema versions and workspace activation; see ADR-020. Static dependency checking is in `backend/tools/check-boundaries.php`; SQL coupling and tenant escape still require review/integration evidence. M01 had no business module; M03 implements only Parties and Catalog. Shared event infrastructure owns its tables and journal consumer, not module-private records.
