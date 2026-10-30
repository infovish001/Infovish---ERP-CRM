# Sales

Bundled M05 module owning company tax profiles, quotations with ordered JSON line snapshots, immutable confirmed orders and history. Privileged MFA users mutate; granted-company members/auditors read. Public Orders/OrderData returns immutable authorized values for future Finance/Inventory consumers. Dependencies: Parties Contacts, Catalog Products, CRM Opportunities. No private cross-module persistence access.

Draft → issued → converted creates one confirmed order; draft/issued → cancelled requires a reason. Issued content and confirmed orders are immutable. Snapshot-based PDFs are authenticated commercial documents, not tax invoices. VAT classification is explicit and tied to seller registration and a source-versioned Sri Lanka policy. No stock/accounting/communications effects. See M05_SPEC, M05_SETUP and SRI_LANKA_TAX_RULES for exact behavior, rounding and exclusions.
