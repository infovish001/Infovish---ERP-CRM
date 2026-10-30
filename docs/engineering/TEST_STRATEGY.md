# Foundation verification strategy

Unit/feature tests cover fail-closed tenant context, process health, dependency success/failure envelopes, sensitive-error omission, correlation header, Inertia component/props and production UI denial. Facade mocks verify response behavior only; they do not count as live PostgreSQL or Redis evidence.

Integration tests are opt-in locally with `RUN_INTEGRATION=1`, mandatory in CI, and reject any PostgreSQL database other than `infovish_test`. They exercise migrations, rollback/reapply, transactional write rollback, a real Redis connection and a uniquely named Redis queue job. Never use a production database, credentials or tenant data for tests. Skipped tests remain unverified.

Frontend checks use strict TypeScript, ESLint, a production asset build and interaction tests for dependency failure display, theme and responsive navigation controls. Browser inspection supplements these tests; WCAG 2.2 AA, keyboard/assistive-technology coverage and full browser compatibility remain later release gates.

Static boundary checks validate future module manifests and cross-module import seams. They cannot prove dynamic SQL or tenant isolation. M02 adds actual isolation/authorization tests; business milestones add invariant, concurrency and idempotency tests. No fake feature or test count should substitute for these gates.

GitHub Actions uses ephemeral PostgreSQL/Redis, mandatory integration tests, Composer/npm audits and Gitleaks CLI history scanning. A local command pass does not imply a hosted CI run passed. Record every required check as PASS, FAIL, BLOCKED or NOT RUN in M01_REPORT.md and retain commands and limitations.
