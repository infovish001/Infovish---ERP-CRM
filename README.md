# Infovish ERP and CRM

Infovish Systems' incremental modular ERP platform. M00 through M04 are verified. M05's Sales quotation-to-order baseline is verified, including source-reviewed ordinary Sri Lankan VAT policies, immutable confirmed orders and authenticated PDFs. R1 commercial release remains gated; Finance, Inventory and production deployment are future work.

Read [M00 report](docs/engineering/M00_REPORT.md), [architecture validation](docs/architecture/ARCHITECTURE_VALIDATION.md), [technology decisions](docs/architecture/TECHNOLOGY_DECISIONS.md) and [milestones](docs/engineering/MILESTONES.md). Original Word references remain in `References/`; complete paragraph/table-text transcriptions are in `docs/requirements/`. Embedded diagrams remain in the originals.

The Laravel application lives in `backend/`. React source has one canonical location: `backend/resources/js`. Platform administration and portals are future applications; no empty application directories are generated.

See [local setup](docs/engineering/LOCAL_SETUP.md), [M02 operator setup](docs/engineering/M02_SETUP.md) and [M02 verification report](docs/engineering/M02_REPORT.md) for commands and actual limitations. See [M03 setup](docs/engineering/M03_SETUP.md) and [M03 report](docs/engineering/M03_REPORT.md). See [M04 setup](docs/engineering/M04_SETUP.md) and [M04 report](docs/engineering/M04_REPORT.md). Stop before M05; it requires explicit authorization.

M05 implements the Sales quotation-to-order baseline with source-reviewed ordinary Sri Lankan VAT policies and authenticated PDFs. See [M05 setup](docs/engineering/M05_SETUP.md), [M05 report](docs/engineering/M05_REPORT.md) and [R1 release gates](docs/engineering/R1_RELEASE_GATES.md). M05 functional verification passed; R1 remains unreleased. Stop before M06.
