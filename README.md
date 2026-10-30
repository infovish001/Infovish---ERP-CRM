# Infovish ERP and CRM

Infovish Systems' incremental modular ERP platform. Architecture validation (M00) and the engineering foundation (M01) are verified. M02 adds tenant provisioning, identity, organization and audit. M03 adds bundled modules, contacts/catalog and transactional shared services; verification evidence is recorded in each milestone report. Business modules and production readiness remain future milestones.

Read [M00 report](docs/engineering/M00_REPORT.md), [architecture validation](docs/architecture/ARCHITECTURE_VALIDATION.md), [technology decisions](docs/architecture/TECHNOLOGY_DECISIONS.md) and [milestones](docs/engineering/MILESTONES.md). Original Word references remain in `References/`; complete paragraph/table-text transcriptions are in `docs/requirements/`. Embedded diagrams remain in the originals.

The Laravel application lives in `backend/`. React source has one canonical location: `backend/resources/js`. Platform administration and portals are future applications; no empty application directories are generated.

See [local setup](docs/engineering/LOCAL_SETUP.md), [M02 operator setup](docs/engineering/M02_SETUP.md) and [M02 verification report](docs/engineering/M02_REPORT.md) for commands and actual limitations. See [M03 setup](docs/engineering/M03_SETUP.md) and [M03 report](docs/engineering/M03_REPORT.md). Stop before M04; it requires explicit authorization.
