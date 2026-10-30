# Requirements traceability

Baseline: PRD v1.1, SAD v1.0 and master request. Range identifiers are preserved because the PRD does not define each individual requirement separately. Foundation evidence is partial support of cross-cutting controls, not implementation of entire requirement ranges. Detailed field-level acceptance scenarios must be approved before each business milestone.

| Requirement/source | Milestone | Artifact or check | Status |
|---|---|---|---|
| Master M00, SAD section 14 | M00 | Architecture validation, technology review, ADR-001–018 | Reviewed; foundation decisions selected |
| SAD section 6 | M00/M01 | Canonical `backend/resources/js`, ADR-003 | Implemented |
| SAD-AC-001 | M01 | Compose, setup scripts, LOCAL_SETUP | Docker build/start and live service verification recorded in M01 report |
| NFR-005, SEC-008 | M01 | CI formatting/static/frontend/scan jobs, lockfiles | Hosted CI and secret scanning passed; see linked run in M01 report |
| NFR-006 | M01 | PostgreSQL migrations and integration round trip | Verified on platform/test databases; tenant fleet migrations remain deferred |
| NFR-007, ARC-BE-010 | M01 | JSON formatter, generated correlation IDs, sanitized readiness | Foundation only; audit M02 |
| SAD-AC-002, ARC-BE-004 | M01 | Module convention and static import/manifest checker | Foundation only; zero business modules |
| SAD-AC-013, NFR-003 | M00/M01 | Versioned API/event design; health OpenAPI | M02 adds versioned identity/organization APIs; later business APIs deferred |
| NFR-015 | M01 | Responsive shell, theme, semantic controls and keyboard access | Preliminary; no WCAG conformance claim |
| TEN/IAM/ORG ranges, BR-TEN, NFR-001/004/006/007, SEC-001 | M02 | M02_SPEC, ADR-019, tenant/identity/organization services and real PostgreSQL/Redis tests | Implemented baseline; verification and remaining gates in M02_REPORT. SSO, field permissions and broader enterprise scope deferred |
| MOD/ORG baseline, Parties/Catalog, DEP-001 through DEP-005/007, NFR-002/003/009/013 | M03 | ADR-020, module manifests/activation/entitlements, public DTOs, contacts/products, shared transactions and real integration tests | Implemented baseline; final verification and deferred features in M03_REPORT |
| CRM baseline, BR-CRM/AC-CRM lifecycle/conversion, NFR-002/003/007/009/013 | M04 | ADR-021, assigned CRM services, public-contract conversion, immutable history, exact forecasts, integration/UI tests | Implemented baseline; verification and advanced CRM exclusions in M04_REPORT |
| SAL quotation/order baseline, E2E-004/005, NFR-001/002/003/006/007/009/013, DEP-001/002/004/005/007 | M05 | ADR-022, Sales snapshots/pricing/profile/PDF/conversion and integration/UI tests | Implemented baseline; evidence in M05_REPORT; R1 gates remain open, no release claim |
| FIN/BR-FIN/AC-FIN grouped baseline, E2E-006/007/010/019, NFR consistency/precision/isolation/audit | M06 | M06_SPEC, ADR-023, explicit setup/immutable ledger/subledgers/reconciliation/reports, sourced tax review and integration/UI checks | Implemented scoped baseline; actual verification in M06_REPORT; statutory statements/corrections, FX/closing and production certification deferred |
| INV/PRC ranges, BR-INV | M07 | Ownership and event boundaries | Deferred |
| HRM/PRJ/HLP/POR/DMS ranges | M08 | Module ownership table | Deferred |
| COM/AUT/MKT ranges, BR-COM | M09 | Official provider trust boundary | Deferred |
| AI range, BR-AI, SAD-AC-008 | M10 | Governed tool/approval boundary | Deferred |
| MFG/POS/ECM/SUB/LOG/FLT/RNT/AST ranges | M11+ | Domain contracts pending | Deferred |
| STU/IND ranges | M12 | Metadata/industry isolation ADRs | Deferred |
| CLD range, NFR-008/010/011/012/014/017/018/020 | M13 or earlier release gates | Deployment and recovery objectives | Unverified; no service guarantees |

No requirement is Released. Verification outcomes, commands and known blockers are maintained in the milestone reports. BI functionality and competitor parity remain unimplemented/unverified; no Odoo/Flectra edition or feature assertion is made.
