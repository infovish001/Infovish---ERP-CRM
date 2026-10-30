# Implemented module catalog

| Module | Version | Dependencies | Public contracts | Delivered baseline |
|---|---|---|---|---|
| Parties | 1.0.0 | None | Contacts, ContactData, NewCustomer | Company-scoped contact/customer/supplier identities and explicit customer conversion contract |
| Catalog | 1.0.0 | None | Products, ProductData | Product/service definitions, variants, optional decimal list prices |
| CRM | 1.0.0 | Parties ^1.0 | Opportunities, OpportunityData | Assigned company-scoped leads, pipelines, opportunities, activities and estimates (schema 3) |
| Sales | 1.0.0 | Parties/Catalog/CRM ^1.0 | Orders, OrderData, BillingData | Sri Lanka tax profiles, exact quotations, authenticated PDFs and immutable confirmed orders; authorized billing DTO (schema 4+) |
| Finance | 1.0.0 | Parties/Sales ^1.0 | Ledger, BalanceData | Explicit LKR setup, immutable balanced ledger, invoice/bill posting, retained invoice PDFs, recorded payments, manual reconciliation and management reports (schema 5) |

Only these five code-bundled modules are allowlisted. Entitlement, installed version, activation and authorization independently gate access. Public DTOs are immutable and contain no persistence model. Dependency graphs and activation/deactivation ordering are checked. Operator-only schema installation is separate from activation, which runs no DDL or arbitrary hooks. CRM public reads enforce assignment scope; privileged conversion uses one tenant transaction across public module contracts. Inventory and subsequent milestones remain unimplemented.

M05 adds bundled Sales 1.0.0 (schema 4), dependent on Parties/Catalog/CRM public contracts. Owns tax profiles, quote/line JSON snapshots, immutable orders and history; Orders/OrderData is the future integration boundary. Catalog Products.available and CRM Opportunities.openForCustomer return authorized DTO choices. Finance/inventory effects remain absent. See M05_SPEC/M05_SETUP for pricing and permissions.

M06 consumes Sales BillingData and Parties contact/supplier DTOs without private-table access. Sales itself still creates no Finance side effects. Explicit Finance posting owns all ledger/subledger effects. The read-only Ledger balance contract returns decimal strings; no persistence model escapes. See M06_SPEC/M06_SETUP for supported tax-invoice scope, role rules and exclusions.
