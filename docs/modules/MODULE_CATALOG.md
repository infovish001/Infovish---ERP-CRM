# Implemented module catalog

| Module | Version | Dependencies | Public contracts | Delivered baseline |
|---|---|---|---|---|
| Parties | 1.0.0 | None | Contacts, ContactData | Company-scoped contact/customer/supplier identities |
| Catalog | 1.0.0 | None | Products, ProductData | Product/service definitions, variants, optional decimal list prices |

Only these code-bundled modules are allowlisted. Entitlement, installed version, activation and authorization independently gate access. Public DTOs are immutable and contain no persistence model. Controlled tests verify dependency cycles and activation/deactivation ordering even though the two current modules have no artificial dependency. Module schemas are installed by the operator; activation never runs DDL or arbitrary hooks. CRM and later modules remain unimplemented.
