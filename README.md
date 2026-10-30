# Infovish ERP and CRM

Infovish Systems' incremental modular ERP platform. This repository begins with architecture validation (M00) and the engineering foundation (M01). It is not a production ERP release.

Read [M00 report](docs/engineering/M00_REPORT.md), [architecture validation](docs/architecture/ARCHITECTURE_VALIDATION.md), [technology decisions](docs/architecture/TECHNOLOGY_DECISIONS.md) and [milestones](docs/engineering/MILESTONES.md). Original Word references remain in `References/`; complete paragraph/table-text transcriptions are in `docs/requirements/`. Embedded diagrams remain in the originals.

The Laravel application lives in `backend/`. React source has one canonical location: `backend/resources/js`. Platform administration and portals are future applications; no empty application directories are generated.

See [local setup](docs/engineering/LOCAL_SETUP.md) and [M01 verification report](docs/engineering/M01_REPORT.md) for commands and actual limitations. Do not start M02 without explicit authorization.
