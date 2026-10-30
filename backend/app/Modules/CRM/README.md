# CRM

Owns crm_leads, crm_pipelines, crm_stages, crm_opportunities, crm_activities and append-only crm_history. Its schema-3 module migration is additive. Depends on Parties ^1.0 public Contacts/ContactData/NewCustomer contracts for explicit customer lookup/creation; no private Parties table query or model import is permitted.

Public contract: Opportunities with immutable OpportunityData. Lookups enforce current tenant/company/module/dependency/MFA and member assignment scope. Consumers receive stable identifiers and primitive decimal strings, never a persistence model. M05 must independently authorize any future Sales action; a CRM won state is not an order or accounting posting.

Members write assigned records in granted active companies; auditors read company scope; privileged MFA users reassign, convert, configure pipelines and reopen. Lead/opportunity lifecycle, duplicate email handling, revisions, assignment and parent-stage validation run server-side within tenant transactions. Conversion uses the same database transaction across public module contracts and locks one qualified lead, producing at most one opportunity. Explicit reuse/new-customer intent is persisted by hash; matching retries return the original result, conflicts fail.

Shared numbering allocates LED/OPP references; Mutation retains its privileged default for other modules and explicitly allows CRM's narrower permission. CRM mutations atomically record allowlisted audit, immutable module history and crm.* v1 ID-only outbox events. Activity/history detail previews are bounded to 100 records; data is retained. Forecasts are permission-scoped exact NUMERIC estimates using explicit probabilities and per-record half-up rounding to six places, not recognized revenue.

No external capture, fuzzy merge, Sales transaction, AI scoring, territory, commission, communications or automation behavior is claimed. Verification and limits: docs/engineering/M04_REPORT.md.
