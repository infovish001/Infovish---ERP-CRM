# M04 CRM baseline

Authorized by user continuation after M03 review on 2026-10-30. Refines the PRD CRM range, BR-CRM-01 through BR-CRM-04, AC-CRM lifecycle/conversion, NFR-001/002/003/007/009/013 and SAD module/API/event boundaries. Scenario labels below elaborate the grouped requirements, not new individual PRD IDs. No independent owner approval is claimed.

## Scope and behavior

CRM is a bundled module depending on Parties ^1.0 and tenant schema 3. Upgrade is additive through the existing operator path; existing organization/master-data modules support schemas 2 and 3. CRM is installed/entitled but initially inactive, with no seeded leads, pipelines or illustrative business values. Owner activates Parties then CRM and creates real company pipelines with explicit stage probabilities.

Company-scoped leads contain permanent numbers, title/contact name, optional normalized email/phone/source, owner assignment and revision. States are new, qualified, lost, converted. Members can create for themselves and read/edit only assigned records in explicitly granted active companies; auditors read authorized companies without writes; privileged MFA users manage company CRM records/assignment. Only active writable company members can be assigned. Qualify/new-to-lost actions are explicit; loss requires a reason. Privileged reopen requires a reason. Converted leads preserve their conversion link and cannot be edited/reopened. Active duplicate email is rejected within a company with a generic duplicate message, without exposing an inaccessible assignee's record. No fuzzy merge or automatic contact reuse is performed.

Privileged conversion locks a qualified lead, explicitly creates a customer through Parties' public contract or selects an active existing customer, and creates one opportunity in the chosen active pipeline/stage. Existing-email collisions require explicit customer selection; never silently merge identities. Contact, opportunity, lead state, numbers, idempotency, audit and outbox commit in one tenant transaction. Matching retries, including a second key, return the original opportunity; conflicting conversion intent returns 409. No Sales order/quote is created.

Company pipelines have ordered stages with explicit integer probabilities 0-100. Privileged users create/update/deactivate pipelines/stages; no destructive delete or historical relabeling claim. Opportunities link an authorized active customer through the public contract, an active pipeline/stage, assignment, optional expected amount and expected close date. Amount is a nonnegative NUMERIC(20,6) decimal string in company base currency. Open opportunities can be edited/moved; explicit won/lost close actions record history, loss requires a reason, and privileged reopen requires a reason and active target stage. Closed records are otherwise immutable. Immutable stage/action history and allowlisted audits preserve transitions.

Activities attach to exactly one lead or opportunity in the same company; their access follows the assigned parent. Calls, meetings and tasks have subject, optional note, explicit UTC due time and planned/done/cancelled lifecycle. Members write only assigned parent records; completed/cancelled activities are retained and immutable. Detail views show up to 100 planned/recent activities (planned first, then recently closed) and the latest 100 history entries; earlier rows are retained. External calendar/email/WhatsApp delivery is not implied.

Lists/search and a paginated pipeline board expose only authorized rows. Forecasts are labeled estimates over authorized open opportunities, with known-value count and unknown-value count. Unweighted sum and per-opportunity probability-weighted amounts use PostgreSQL NUMERIC; each weighted value rounds half-up to six places before summation. Explicit company currency only, no currency conversion, accounting revenue, tax or commission calculation. Stage probabilities are operator inputs; forecasts reflect current stage configuration, not historical forecast snapshots.

## Acceptance scenarios

- M04-ACCESS: dependency/activation/entitlement/schema/MFA gates; actor/company/assignment checks; inactive/revoked principals and forged cross-tenant/company IDs fail closed in API and public contracts.
- M04-LEAD: create/edit/qualify/loss/reopen/duplicate handling, assignment and stale revisions; converted immutability; same IDs in separate tenant databases remain isolated.
- M04-CONVERT: explicit new/existing customer conversion, duplicate collision, retry/concurrent conversion, cross-module public contracts, rollback after contact creation and one opportunity per lead.
- M04-PIPELINE: validated stage probabilities/order, active parent checks, moves/won/lost/reopen and immutable history; member/auditor scope.
- M04-ACTIVITY: parent validation, due date/time, create/edit/complete/cancel with role scope and retained history.
- M04-FORECAST: exact large/small decimal values, explicit rounding, unknown amounts, company/assignment filters and non-accounting labels.
- M04-OPS: ID-only CRM events from member authors through real Redis journal delivery, full prior regression, frontend/static/API/boundary/audit/secret checks, host/container/browser and hosted CI. Inspect Figma CRM reference before UI. Upgrade the requested local tenant without inserting sample records. Stop before M05.

Deferred: Sales, external capture, territories, advanced forecast snapshots, fuzzy deduplication/merge, AI scoring/churn, account plans, automation, notifications/calendar sync, marketing and commercial production readiness.
