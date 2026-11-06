# Implementation Prompt: Authentication and Session Engine

## Goal

Implement passenger registration, login, logout, and role-aware session identity for the vanilla PHP airline reservation system. Provide Bootstrap 5 registration and login pages and reusable page partials at `includes/header.php`, `includes/navbar.php`, and `includes/footer.php`.

## Skills read

- `php-pro` — password hashing, PDO, session handling, input validation, and PHP conventions.
- `bootstrap-components` — responsive Bootstrap 5 form and navbar patterns and accessibility.
- `architect` — separation of models, request handlers, and views.
- `imprint` — capture the completed UI's reusable visual patterns.
- `AGENTS.md` — project architecture, security requirements, and implementation approval workflow.

## Existing code inspected

- `AGENTS.md` — application scope, PHP/Bootstrap conventions, and security requirements.
- `config/db.php` — returns an exception-configured PDO connection using `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` from the process environment.
- `database/schema.sql` — `users` already has `id`, `full_name`, unique `email`, `password_hash`, `role ENUM('passenger', 'admin') DEFAULT 'passenger'`, and timestamps.
- `README.md` — documents the current database foundation and local PHP/XAMPP configuration.
- `docs/database-erd.md` — documents the users-to-bookings relationship and account identity fields.
- Existing prompts under `prompts/`.
- No application routes/pages, models, handlers, or `includes/` UI partials exist yet.

## Decisions or assumptions

- Public registration always creates a `passenger`; do not accept or trust a submitted role.
- Admin accounts are provisioned separately by a trusted operator. Admin users can authenticate through the login flow; do not build public admin registration or an invitation/secret flow in this task.
- Use the existing `users` schema as-is; do not add a migration or alter `database/schema.sql`.
- Use the current vanilla PHP/PDO setup without adding a framework, package, or `.env` loader.
- Choose a small, consistent file structure that fits the existing project; do not create a broader MVC framework or implement flight, booking, or admin-management features.
- Use Bootstrap 5 via the existing project-appropriate integration approach (CDN is acceptable); ensure local pages render under the XAMPP project URL.
- Apache must receive the required DB environment variables independently of PowerShell CLI variables, as documented in the README.

## Files likely to change

- `includes/header.php` — shared document head, Bootstrap CSS, and opening page markup.
- `includes/navbar.php` — role/session-aware navigation and authentication links.
- `includes/footer.php` — shared footer, Bootstrap JavaScript bundle, and closing markup.
- New registration and login page/handler files in a minimal, clearly organized structure.
- New user model and small authentication/session/CSRF helpers where needed.
- `ui-registry.md` — record visual patterns from the completed authentication UI.
- No expected changes to `config/db.php`, `database/schema.sql`, or unrelated product features.

## Implementation requirements

- Implement passenger registration with full name, email, password, and password confirmation. Validate required fields, email format, reasonable field lengths, matching passwords, and a minimum password length of 8 characters on the server.
- Normalize email consistently before checking/inserting. Use PDO prepared statements for duplicate checks, account creation, and credential lookup; rely on the unique key as the final duplicate guard and present a safe, useful validation message if it conflicts.
- Hash new passwords with `password_hash()` and verify them with `password_verify()`. Never store or log plaintext passwords.
- Implement login using email and password, retrieve the account's stored role, and return a generic authentication error that does not reveal whether an email exists.
- Treat the database role as authoritative. Store only the minimum identity data needed in the session (such as user ID, full name, and role); never accept role values from request data to establish identity or permissions.
- After successful authentication, call `session_regenerate_id(true)`. Start sessions through one shared, consistently included helper and configure cookie options before session start (`HttpOnly`, `SameSite=Lax`, and `Secure` when the request uses HTTPS).
- Implement logout as a POST action protected by CSRF. Clear session data, expire the session cookie using its configured parameters, and destroy the session.
- Include CSRF tokens in state-changing registration, login, and logout forms and validate tokens server-side before processing POST requests. Do not mutate state on GET.
- Escape dynamic HTML output with `htmlspecialchars()` using UTF-8 and appropriate flags. Preserve safe form values after validation failures, but never repopulate password fields.
- Keep request handling separate from reusable view partials. Redirect after successful registration/login/logout and terminate request handling after redirects.
- Make the navbar reflect guest versus authenticated state and the authenticated user's role; show role-appropriate navigation without implementing protected booking/admin pages.
- Build accessible responsive Bootstrap 5 forms with associated labels, appropriate input types/autocomplete attributes, visible validation feedback, keyboard usability, and mobile-friendly layout.
- Avoid exposing PDO exception details, credentials, stack traces, or sensitive account data in responses. Surface failures using repository-consistent, safe messages and server-side logging where appropriate.
- Do not add a public admin signup form or embed an admin password/hash in source code or committed setup files.
- Add/update directly related documentation only if needed to explain running the auth pages and the out-of-band admin provisioning assumption.

## Security requirements

- Use PDO prepared statements for every query involving user input.
- Use `password_hash()` and `password_verify()`; never store plaintext passwords.
- Apply server-side validation and output escaping with `htmlspecialchars()`.
- Protect all POST forms with CSRF tokens, including login and registration.
- Regenerate session IDs after authentication; use secure cookie attributes and fully clear sessions on logout.
- Enforce roles from the database-backed session identity, never from form/query parameters.
- Public registration may create only the `passenger` role. Admin account creation remains outside public endpoints.
- Do not hardcode database credentials or commit secrets.

## Acceptance criteria

- A new visitor can register a passenger account; its password is stored as a hash and its role is `passenger`.
- Registration safely handles invalid input, duplicate email, and CSRF failure without inserting unintended accounts or exposing database errors.
- Existing passenger and separately provisioned admin accounts can log in with `password_verify()`; invalid credentials produce a generic safe response.
- Successful login regenerates the session ID and maintains the authenticated identity and database role across requests.
- Logout is POST/CSRF protected and destroys the authenticated session.
- Navbar and shared partials render correctly for guests, passengers, and admins without implying that booking/admin pages already exist.
- The registration/login pages use accessible, responsive Bootstrap 5 markup and the shared partials are reusable.
- Public input cannot create or elevate an account to admin.
- Existing schema and database configuration remain compatible and unchanged.

## Checks to run

- Run PHP syntax checks on every new or changed PHP file using the available PHP runtime.
- Run any existing project tests or validation tools; do not add tooling solely for this task.
- If XAMPP MariaDB and Apache are available, verify PDO connectivity using the configured Apache environment and perform registration/login/logout checks using a disposable test account. Do not claim live DB/browser checks unless performed.
- Review every SQL statement for prepared-statement use, every dynamic template output for escaping, and every state-changing form/handler for CSRF checks.
- Check Apache/PHP logs for warnings or errors when runtime verification is available.
- Confirm `ui-registry.md` records the resulting visual patterns without changing unrelated UI.

## Exact manual test steps expected after implementation

1. Start Apache and MariaDB in XAMPP and configure the documented `DB_*` environment values for Apache/PHP.
2. Visit the registration page at its documented `http://localhost/airline-reservation-system/...` URL.
3. Submit an invalid email, missing field, mismatched passwords, and a password shorter than 8 characters; confirm inline/server validation and no user is created.
4. Register a valid passenger with a unique email; confirm success and verify the corresponding database row has role `passenger` and a non-plaintext `password_hash`.
5. Attempt to register the same normalized email again; confirm a safe duplicate message and no second account.
6. Submit a forged role value such as `admin` during registration; confirm the created account remains `passenger`.
7. Tamper with or omit the CSRF token on registration, login, and logout POST requests; confirm each request is rejected without changing authentication/account state.
8. Log in with an incorrect email/password combination; confirm a generic error. Then log in with the valid passenger credentials and confirm the navbar reflects the authenticated passenger.
9. Authenticate with a separately provisioned admin account and confirm the session/navbar reflects the database-backed admin role; verify no public admin signup route exists.
10. Log out using the UI; confirm the session is no longer authenticated and protected role-aware navigation is no longer shown.
11. Resize to mobile and desktop widths and keyboard-navigate both forms and the navbar; confirm labels, focus, validation feedback, and responsive layout remain usable.
