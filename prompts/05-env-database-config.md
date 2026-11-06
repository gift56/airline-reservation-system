# Implementation Prompt: Load Database Settings from a Protected `.env` File

## Goal

Let the XAMPP/Apache application load database settings from a project-root `.env` file, so browser requests no longer depend on manually adding `SetEnv` lines to Apache configuration. Keep real credentials out of Git and prevent Apache from serving the `.env` file.

## Skills read

- `php-pro` — PHP configuration, PDO, and safe error handling.
- `AGENTS.md` — project architecture and credential-protection requirements.

## Existing code inspected

- `config/db.php` — reads `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` exclusively through `getenv()` and then creates a PDO connection.
- `.gitignore` — already ignores `.env`, but does not clearly ignore every `.env.*` variant or explicitly retain a safe example file.
- `README.md` — currently says the project has no dotenv loader and instructs users to configure environment variables for Apache or PowerShell CLI.
- `AGENTS.md` — requires database credentials to be kept out of committed configuration.
- Apache configuration supplied by the user — `mod_env` is enabled; the user wants to avoid editing `httpd.conf`.
- No project `.htaccess` currently exists.

## Decisions or assumptions

- Implement a small, dependency-free PHP loader for the four supported database keys rather than adding Composer or a third-party dotenv package.
- Keep `.env` at the project root as requested, with a committed `.env.example` containing placeholders only.
- An already-defined process environment variable takes precedence over its `.env` value, allowing deployed environments to keep using their existing configuration.
- The project is under Apache's `htdocs` and `AllowOverride All` is enabled there. Add a narrow project-root `.htaccess` rule that denies HTTP requests for `.env` and `.env.*`; test the rule through Apache.
- Do not add a credential, password default, automatic account creation, database migration, or schema change.
- Preserve `config/db.php`'s current PDO settings and its failure behavior for absent required settings, but make the error message explain that `.env` or process environment variables can supply them.

## Files likely to change

- `config/env.php` — load supported variables from the root `.env` file only when missing from the process environment.
- `config/db.php` — load the configuration helper before reading database variables.
- `.gitignore` — ignore `.env` and `.env.*`, while allowing `.env.example`.
- `.env.example` — placeholder template with the four required keys and no working credential.
- `.htaccess` — deny external access to `.env` files.
- `README.md` — document copying/configuring `.env`, Apache restart behavior if needed, security, and connection testing.

## Implementation requirements

- Implement a typed PHP 8.0-compatible parser using built-in PHP only; no dependency installation.
- Read only the fixed allowlist `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS`. Do not execute or `include` the `.env` file as PHP.
- Resolve `.env` relative to the project root, not the current working directory.
- Support blank lines, surrounding whitespace, comments, and single- or double-quoted values. Do not implement shell expansion or variable interpolation.
- Do not overwrite process-level environment values. When a supported key is missing from `getenv()`, load it from `.env`, then expose it consistently to `getenv()` for `config/db.php`.
- Make missing/unreadable file behavior explicit and safe; missing required values must still fail with a useful configuration error and no credential content.
- Preserve PDO exception mode, associative fetch mode, disabled emulated prepares, DSN, and `DB_PASS` empty-string semantics as currently implemented.
- Add `.env.example` with safe placeholders; tell users to copy it to `.env` and replace placeholders locally.
- Add a narrowly scoped Apache access-denial rule for `.env` and `.env.*` files. Do not deny unrelated assets or routes.
- Update README instructions so users do not need to add real credentials to `httpd.conf` or PowerShell for browser auth pages.
- Never print or log credential values or include them in exception text.
- Do not alter authentication, database schema, or other application behavior.

## Security requirements

- Ignore local `.env` and all `.env.*` files in Git, except the explicit `.env.example`.
- Restrict Apache access to `.env` and `.env.*`; verify the live HTTP response is denied (403/404), not the file contents.
- Use a strict key allowlist and parse data as text only.
- Never hardcode real credentials, expose them in logs/output, or commit them.
- Continue using the existing PDO prepared-statement usage and configuration.

## Acceptance criteria

- With a correctly configured project-root `.env`, Apache-served login and registration pages no longer show the missing `DB_HOST` configuration error.
- Process environment settings override `.env` values.
- A missing `.env` or missing required setting fails with a safe, actionable error naming the missing key but not displaying any values.
- The `.env` file is ignored by Git and inaccessible over HTTP; `.env.example` remains trackable and contains placeholders only.
- Existing PDO attributes and schema remain unchanged.
- README gives exact XAMPP setup and verification steps without asking the user to modify Apache's global config.

## Checks to run

- Run PHP syntax checks on changed PHP files using XAMPP PHP.
- Exercise the parser using a temporary test fixture with comments, quoted values, process-variable precedence, and missing keys; never use or print the user's real credentials during this test.
- Confirm `.env` is ignored and `.env.example` is not ignored using `git check-ignore` where Git is available.
- With Apache running, request `/.env` and `/.env.example` under the project URL and confirm neither response reveals file contents.
- If the user's local `.env` is already configured and MariaDB is available, run a PDO connection check without printing any credential. Do not modify or create user credentials.
- Review README and `.htaccess` for accurate behavior and narrow access controls.

## Exact manual test steps expected after implementation

1. Copy the tracked `.env.example` to `.env` in the project root.
2. Replace the placeholder values locally with the existing MariaDB host, database name, username, and password. Do not share or commit `.env`.
3. Keep Apache and MySQL running; reload `http://localhost/airline-reservation-system/login.php`.
4. Confirm the missing-`DB_HOST` warning is gone. If a connection error remains, inspect Apache's `error.log` for the specific non-secret cause (credentials, database name, host, or port).
5. In a browser, visit `http://localhost/airline-reservation-system/.env` and `http://localhost/airline-reservation-system/.env.example`; confirm neither displays file contents.
6. Confirm `.env` is ignored by Git while `.env.example` is visible as a candidate file.
7. Test a valid passenger registration/login/logout flow using the existing application pages once the database connection succeeds.
