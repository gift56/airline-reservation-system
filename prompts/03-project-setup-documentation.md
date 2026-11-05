# Implementation Prompt: Project Setup and Database Compatibility Documentation

## Goal

Update the project instructions to prevent database-version compatibility mistakes and create a detailed root README with accurate XAMPP/MariaDB setup, schema import, PDO configuration, verification steps, and the current implementation status. Include a suggested detailed commit message in the completion response.

## Skills read

- `mysql-schema` — schema compatibility, constraints, and relationship documentation.
- `php-pro` — PDO configuration and environment variables.
- `AGENTS.md` — project prompt-and-approval workflow and documentation requirements.

## Existing code inspected

- `AGENTS.md` — project architecture, security and workflow instructions.
- `database/schema.sql` — current five-table schema and MariaDB triggers.
- `config/db.php` — reads `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` with `getenv()`; no `.env` loader is present.
- `docs/database-erd.md` — ERD, trigger invariants, and FK policies.
- Existing prompts under `prompts/`.
- User confirmed the schema has now been successfully imported using XAMPP.

## Decisions or assumptions

- This repository currently contains the database foundation and project guidance, not a finished passenger/admin application; documentation must not imply that search, registration, booking, or CRUD pages already exist.
- The observed local stack is XAMPP with Apache, PHP 8.4.27, phpMyAdmin 5.2.3, and MariaDB 10.11.16. Label these as verified local versions, not universal requirements for every installation.
- The SQL `#1901` issue is specific to the current observed import path. Document the root cause carefully and require agents to verify the actual target engine/version and test constraint syntax instead of assuming that MySQL and MariaDB support every constraint identically.
- This schema enforces the flight rules through `BEFORE INSERT` and `BEFORE UPDATE` triggers because the local server rejected the relevant `CHECK` expressions. Preserve this distinction in README and AGENTS guidance.
- A `.env` file is not read by the current PHP code. Explain that environment variables must be made available to PHP/Apache by the local runtime; do not claim Composer dotenv support or hardcode credentials.
- Keep all instructions safe for an existing database; do not recommend destructive database resets as routine troubleshooting.
- Give a suggested commit title and body in the final response, but do not create a commit.

## Files likely to change

- `AGENTS.md`
- `README.md` (new)
- `prompts/03-project-setup-documentation.md` (this approved implementation prompt)

## Implementation requirements

- Add a database compatibility section to `AGENTS.md` requiring inspection of the actual DB engine/version, syntax validation against that target, and preservation of integrity invariants when changing constraint implementation.
- Explicitly document the observed MariaDB `#1901` failure and require using a target-compatible strategy (triggers for this project's current schema) rather than blindly removing validation or repeating incompatible `CHECK` constraints.
- Create a detailed README covering:
  - Product purpose and current scope/status.
  - Requirements and the verified local XAMPP versions.
  - Starting Apache/MariaDB, visiting localhost/phpMyAdmin, creating `airline_reservation`, importing the schema, and verifying all five tables/triggers.
  - Existing server/port conflicts and the fact that Apache ports and MySQL port are separate.
  - Database environment variables and the absence of automatic `.env` loading.
  - Safe CLI PDO connection test using PowerShell with temporary environment variables, explaining it tests CLI PHP only.
  - Schema/ERD references, database tables and key relationships.
  - Trigger-enforced flight invariants and the reason.
  - Re-import behavior (`CREATE TABLE IF NOT EXISTS`, named triggers replaced); no table/data deletion, and no repair of mismatched pre-existing definitions.
  - Troubleshooting for access denied, MySQL port conflicts, missing PHP PDO MySQL extension, and failed schema imports.
  - A manual verification checklist and explicit statement that application workflows are not implemented yet.
- Keep docs accurate to checked-in code; do not imply Apache inherits PowerShell-local environment values, that `.env` is automatically parsed, or that a public web test route exists.
- Use Markdown links/relative paths within README where useful.

## Security requirements

- Never put real passwords or credentials in README, AGENTS, or committed configuration.
- State that `root` with an empty password is only a possible local XAMPP default; users must configure the credentials actually used.
- Do not tell users to kill unknown database processes or delete data directories/databases.
- Keep documentation aligned with PDO prepared statements, output escaping, and password hashing rules already in AGENTS.

## Acceptance criteria

- `AGENTS.md` has reusable version/engine compatibility guidance that would prevent blindly repeating the MariaDB `CHECK` mistake.
- README provides an end-to-end local setup and test guide accurate to the current repository and user-confirmed import.
- README clearly distinguishes tested local versions from general requirements and the database foundation from future app features.
- Documentation contains no secrets, unsupported claims, or destructive setup instructions.
- A detailed, ready-to-use commit message is supplied in the final response and no commit is created.

## Checks to run

- Review README and AGENTS references against the current files and schema.
- Confirm Markdown links refer to existing project files or clearly identified external URLs.
- Documentation-only changes require no build or runtime test.

## Exact manual test steps expected after implementation

1. Read the root README from prerequisites through database verification and follow it with the existing XAMPP installation.
2. Verify phpMyAdmin connects to MariaDB and displays the `airline_reservation` database.
3. Confirm all five tables are visible and `SHOW TRIGGERS LIKE 'flights';` displays both validation triggers.
4. Run the documented CLI PDO test after setting temporary PowerShell variables and confirm it reports success, if using the local default credentials.
5. Compare the README database compatibility notes with `docs/database-erd.md` and `database/schema.sql`.
6. Confirm the instructions do not require dropping the already populated local database.
