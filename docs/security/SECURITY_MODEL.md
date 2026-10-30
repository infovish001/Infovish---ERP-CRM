# Foundation security model

M01 is local development infrastructure. No authentication, tenant membership or production security readiness is claimed. The foundation page is restricted to local/testing and contains only dependency state. Liveness/readiness responses deliberately omit hostnames, exception messages and secrets. Restrict probe access at the production edge; they do not authorize business access.

Tenant context fails closed without verified identity. M02 must own trusted routing, membership, lifecycle, role/company/record/field authorization and context cleanup across requests and workers. No tenant connection resolver, provisioning credentials or business endpoints are present in M01.

Development credentials and application keys are randomly generated in ignored `.env` files. CI has isolated ephemeral service credentials. Never copy either to production. Production requires TLS, secure cookies, trusted proxies/hosts, a managed secret store, rotation, redacted structured logs, backups, restoration drills and scoped security assessment. Do not log bodies, authorization headers, provider credentials or business personal data. JSON formatting alone does not redact arbitrary developer-supplied messages; review logging call sites.

The Docker development image runs as a non-root application user but contains development dependencies and the development server. PostgreSQL/Redis ports bind to loopback only. Redis licensing must be reviewed before commercial deployment. No production deployment workflow exists.

Dependency audits and secret scanning are configured in CI. Version tags should be locked to reviewed action/image digests before enabling release deployment. No secrets should be present in source. Future financial/inventory writes require approved domain rules, decimal precision, transactions, locking and idempotency; future AI and communications must preserve their separate trust boundaries.
