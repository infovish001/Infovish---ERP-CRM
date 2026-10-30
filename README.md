# Infovish ERP and CRM

Infovish Systems' incremental modular ERP platform. M00 through M06 functional baselines are verified. M06 adds the scoped LKR Finance ledger, invoice/bill posting, retained invoice PDFs, recorded payments, manual reconciliation and management reports. Verification and limits are recorded in the M06 report. R1 commercial release remains gated; Inventory and production deployment are future work.

Read [M00 report](docs/engineering/M00_REPORT.md), [architecture validation](docs/architecture/ARCHITECTURE_VALIDATION.md), [technology decisions](docs/architecture/TECHNOLOGY_DECISIONS.md) and [milestones](docs/engineering/MILESTONES.md). Original Word references remain in `References/`; complete paragraph/table-text transcriptions are in `docs/requirements/`. Embedded diagrams remain in the originals.

The Laravel application lives in `backend/`. React source has one canonical location: `backend/resources/js`. Platform administration and portals are future applications; no empty application directories are generated.

See [local setup](docs/engineering/LOCAL_SETUP.md), [M02 operator setup](docs/engineering/M02_SETUP.md) and [M02 verification report](docs/engineering/M02_REPORT.md) for commands and actual limitations. See [M03 setup](docs/engineering/M03_SETUP.md) and [M03 report](docs/engineering/M03_REPORT.md). See [M04 setup](docs/engineering/M04_SETUP.md) and [M04 report](docs/engineering/M04_REPORT.md).

M05 implements the Sales quotation-to-order baseline with source-reviewed ordinary Sri Lankan VAT policies and authenticated PDFs. See [M05 setup](docs/engineering/M05_SETUP.md), [M05 report](docs/engineering/M05_REPORT.md) and [R1 release gates](docs/engineering/R1_RELEASE_GATES.md). M06 operations and evidence are in [Finance setup](docs/engineering/M06_SETUP.md), [tax review](docs/engineering/M06_TAX_REVIEW.md) and [M06 report](docs/engineering/M06_REPORT.md). Actual company setup remains explicit; no guessed chart, registration or transactions are installed. Stop before M07.
