# Implemented module catalog

| Module | Version | Dependencies | Public contracts | Delivered baseline |
|---|---|---|---|---|
| Parties | 1.0.0 | None | Contacts, ContactData, NewCustomer | Company-scoped contact/customer/supplier identities and explicit customer conversion contract |
| Catalog | 1.0.0 | None | Products, ProductData | Product/service definitions, variants, optional decimal list prices |
| CRM | 1.0.0 | Parties ^1.0 | Opportunities, OpportunityData | Assigned company-scoped leads, pipelines, opportunities, activities and estimates (schema 3) |

Only these three code-bundled modules are allowlisted. Entitlement, installed version, activation and authorization independently gate access. Public DTOs are immutable and contain no persistence model. Dependency graphs and activation/deactivation ordering are checked; active CRM depends on Parties. Operator-only schema installation is separate from activation, which runs no DDL or arbitrary hooks. CRM public reads enforce assignment scope; privileged conversion uses one tenant transaction across public module contracts. Sales and subsequent milestones remain unimplemented.

M05 adds bundled Sales 1.0.0 (schema 4), dependent on Parties/Catalog/CRM public contracts. Owns tax profiles, quote/line JSON snapshots, immutable orders and history; Orders/OrderData is the future integration boundary. Catalog Products.available and CRM Opportunities.openForCustomer return authorized DTO choices. Finance/inventory effects remain absent. See M05_SPEC/M05_SETUP for pricing and permissions.
