# M02 tenancy identity organization and audit

Authorized by the user's continuation following the verified M01 handoff on 2026-10-30. This specification elaborates PRD TEN/IAM/ORG ranges, BR-TEN, NFR-001/004/006/007 and SAD sections 4, 5 and 10. The identifiers below are implementation acceptance scenarios, not invented individual PRD requirement definitions. Architecture-lead selections are made under the user's implementation mandate; no separate owner sign-off is claimed.

## Scope and models

The platform database owns UUID users (normalized email, hashed password, active flag, session version, encrypted Fortify MFA fields), tenants (lifecycle, encrypted unique database credentials and schema version), registered domains, memberships (role, active flag, explicit company/branch grants) and platform audit events. No public registration or browser-driven provisioning is exposed.

Each tenant database owns a verified identity row, companies, branches, departments and immutable audit events. Company codes are unique within a tenant; branch/department codes are unique within a company. Base currency is an uppercase three-letter code and timezone an IANA identifier; no tax, accounting, payroll or exchange-rate calculation is introduced. Currency/timezone and stable codes are immutable after creation in M02. Organization records are deactivated rather than erased.

Roles are owner, administrator, auditor and member. Owners/administrators manage organization records; auditors read authorized organization records and audit history; members read authorized organization records. Only owners manage memberships. Non-privileged roles receive explicit company/branch grants; empty grants give no organization access. Membership changes serialize through a per-tenant transaction lock and cannot deactivate or demote the last active owner. Privileged roles require confirmed TOTP MFA before entering the workspace or managing records.

## Security and operations

Resolve an exact registered hostname, ignoring client tenant IDs and forwarded-host hints. Revalidate lifecycle, user state, session version and active membership on every authenticated request. Bind login sessions to the tenant and use host-only cookies. Use Laravel/Fortify session rotation, password confirmation, login/challenge rate limiting and CSRF protection. Disable public registration, persistent remember-me and mail-dependent reset flows until delivery is configured. Password changes require the current password and revoke other sessions. SSO/ABAC/field permissions remain later scope.

The runtime platform role cannot create databases or roles. Provisioning is a separately credentialed operator CLI with an advisory lock and retryable lifecycle; it preserves partial resources on failure. Tenant application roles cannot connect to another tenant database, create schema, or change audit history. Credentials are generated, encrypted in the platform registry and never emitted in command output. Production must inject provisioning credentials only into its privileged process, never into web/worker environments.

Tenant connections open only after authenticated authorization and verify the database identity row. Context and connections are cleared before/after requests and jobs, including exception paths. Cache/storage wrappers require context and namespace by tenant UUID. Jobs revalidate tenant and actor membership when consumed. Audit writes contain allowlisted action/resource metadata, actor and correlation IDs; never passwords, tokens, MFA secrets or database credentials. Audit rows reject update/delete at the database layer.

## Acceptance scenarios

- M02-TEN-A: Provision two real PostgreSQL databases/roles; rerun provisioning safely; record schema/lifecycle status. Suspend/resume without data loss.
- M02-TEN-B: Unknown hosts, absent/inactive memberships, inactive users, suspended tenants and mismatched database identities fail closed. Client tenant headers/body parameters cannot change routing.
- M02-TEN-C: Tenant roles cannot connect to each other's databases; duplicate record identifiers across tenants still isolate reads/writes. Request/job exceptions and reused workers leave no residual context.
- M02-IAM-A: Login/logout, generic failure, throttling, session regeneration, tenant/session binding, password changes and session revocation work. Privileged users must enroll/confirm TOTP; challenge and recovery flows use Fortify.
- M02-IAM-B: Role/company/branch checks run server-side; revoked membership takes effect immediately; last-owner invariants survive concurrent changes.
- M02-ORG-A: Authorized company, branch and department create/update/deactivation and validated scope switching persist; forged/cross-company IDs are denied.
- M02-AUD-A: Sensitive mutations and identity/lifecycle events are audited; disallowed secrets are excluded; audit update/delete attempts fail.
- M02-OPS-A: Host and container regressions, static/frontend checks, API validation, dependency/secret scans and hosted CI pass. Provisioning privileges remain absent from runtime processes.

Inspect the supplied Figma visual reference before UI work. Extend its observed navy sidebar, compact header and enterprise forms; use actual organization data only. Stop before M03. Commercial readiness, backups/restores, cloud-scale connection budgets and broader penetration testing remain separate release gates.
