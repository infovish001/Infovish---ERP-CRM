# Module convention

Add modules only in their authorized milestone. A module owns its data, migrations and invariants. Use Domain, Application, Infrastructure and Presentation layers only where they contain actual code. Each module has a README, tests and `module.json` with a stable `name` and `dependencies` list.

Other modules may import only its `Domain/Contracts` interfaces/DTOs. Consumers may not use another module's private models, repositories or tables. Public contracts expose identifiers and immutable data, never Eloquent models. Version events and API contracts. Prefer explicit dependency declarations; M03 resolves only code-bundled manifests through the explicit module provider/allowlist, checks versions/dependency cycles, and enforces operator entitlements plus tenant activation/schema readiness. Browser activation runs no DDL or arbitrary code.

Run `php tools/check-boundaries.php`. M03 has Parties and Catalog; the checker validates declared dependencies and allowlisted public contract imports. Negative fixtures prove that private and undeclared imports are rejected. It cannot prove runtime SQL ownership, so code review and integration tests remain necessary.
