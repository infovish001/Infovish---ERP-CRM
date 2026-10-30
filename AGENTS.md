# Infovish ERP engineering instructions

Read the PRD, SAD, architecture decisions, traceability and current milestone specification before work. Original references are in `References/`; searchable transcriptions are in `docs/requirements/`.

Implement only the authorized milestone. M00 through M04 passed; M04 evidence is in `docs/engineering/M04_REPORT.md`. Stop before M05 and obtain explicit authorization for every subsequent milestone. Stop at milestone boundaries unless continuation was explicitly authorized.

Preserve tenant and module boundaries. Resolve tenant identity from trusted routing and authenticated membership; never trust a client tenant ID. Authorize protected operations server-side. Never modify another module's private tables; use public contracts or versioned events.

Keep business rules server-authoritative. Use decimal arithmetic and explicit rounding for financial values. Do not invent accounting, payroll or tax rules. Use transactions, locking and idempotency where integrity requires them.

Never hardcode credentials or commit production secrets. Redact logs and errors. AI must use authorized ERP tools and must never receive tenant database credentials. Sensitive AI actions require human approval.

Write readable, purposeful code and comments. Avoid empty scaffolding, fabricated functionality and unnecessary abstractions. Write meaningful tests, report actual PASS/FAIL/BLOCKED/NOT RUN evidence, and never claim unfinished work is complete.

Inspect and follow the approved Figma reference for UI. Do not copy fictitious business metrics into the product. Keep a single React source tree in `backend/resources/js`.

Maintain requirements traceability, architecture and engineering documentation, changelog and milestone reports. Document conflicts and ADR status honestly. Run relevant formatting, static analysis, tests, type checks, build, boundary, secret and dependency checks before reporting completion.
