# Technology decisions

Verified 2026-10-30 using official documentation and registry metadata; exact resolved dependencies are recorded in lockfiles after installation. Select stable releases, not release candidates.

| Component | Foundation selection | Evidence and reason |
|---|---|---|
| PHP | 8.5, CLI observed 8.5.10 | [PHP support](https://www.php.net/supported-versions.php); matches Laravel 13 supported PHP 8.3–8.5 |
| Laravel | 13.x | [Release/support policy](https://laravel.com/docs/13.x/releases); security support to March 17, 2028 |
| Composer | 2.x, observed 2.10.3 | Composer resolves PHP constraints; commit lockfile |
| React | 19.x | [Versions](https://react.dev/versions); Inertia 3 React adapter requires React/DOM ^19 |
| Inertia | 3.x, both adapters | [Server](https://inertiajs.com/docs/v3/installation/server-side-setup), [client](https://inertiajs.com/docs/v3/installation/client-side-setup); Laravel adapter metadata supports Laravel ^13 and PHP ^8.2 |
| TypeScript | 6.0.x, strict mode | [Project installation](https://www.typescriptlang.org/download/); registry latest 7.0.2 is outside typescript-eslint 8.72.1 peer range >=4.8.4 <6.1.0. Select compatible 6.0.x; do not bypass peer checks |
| Vite | 8.x | [Guide](https://vite.dev/guide/); Node 20.19+/22.12+, registry 8.3.5; Laravel Vite plugin 3.x requires Vite ^8 |
| Tailwind | 4.x and Vite plugin | [Official integration](https://tailwindcss.com/docs/installation/using-vite) |
| Node | 24 LTS, observed 24.21.0 | [Release policy](https://nodejs.org/en/about/previous-releases); meets Vite engine constraints |
| PostgreSQL | 17, current patch | [Support policy](https://www.postgresql.org/support/versioning/); 17.11 listed supported through November 8, 2029; [Laravel drivers](https://laravel.com/docs/13.x/database) |
| Redis | 8.10 stable evaluation baseline, Predis PHP client | [Release notes](https://redis.io/docs/latest/operate/oss_and_stack/stack-with-enterprise/release-notes/redisce/); evaluate licensing before commercial hosting; no RC |
| Docker | Engine 29 / Compose plugin; observed CLI 29.6.2, Compose 5.3.1 | [Installation](https://docs.docker.com/compose/install/); local Linux engine initially unavailable |

Compatibility is checked in two stages: upstream supported constraints at M00, actual lockfile resolution and executable checks at M01. Missing local services or PHP extensions are environment blockers for integration evidence, not proof of compatibility failure. Horizon is deferred; basic queue worker is sufficient. Python/FastAPI and Flutter versions will be validated in their implementation milestones, not installed now.

Local inventory: Windows 10.0.26340 x64; Git 2.55.0.windows.3. PHP initially lacks pdo_pgsql, fileinfo and zip; configure project-local INI or use Docker without changing system PHP settings. No existing code or Git repository was present. Package registry queries require network escalation in this sandbox.

Resolved verification baseline: Laravel 13.39.0; Inertia Laravel 3.5.2; Predis 3.7.0; React 19.3.1; Inertia React 3.9.1; TypeScript 6.0.3; ESLint 10.13.0; typescript-eslint 8.72.1; Vite 8.3.5; Tailwind 4.3.3; Vitest 5.0.5. Selected ESLint 10 after npm flagged 9 as unsupported. Exact lockfiles and successful checks are documented in M01_REPORT.md.
