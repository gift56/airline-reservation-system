# Implementation Prompt: Admin Flight CRUD Dashboard

## Goal

Build an administrator-only dashboard for creating, listing, updating, and deleting flights. Provide the requested flight fields: flight code, origin and destination airports, departure and arrival times, seat capacity, and ticket price. Protect all dashboard operations so only an authenticated admin can access them.

## Skills read

- `php-pro` — vanilla PHP conventions, PDO, input validation, sessions, and safe error handling.
- `architect` — separate request handling, model operations, and view rendering.
- `bootstrap-components` — responsive Bootstrap 5 forms, tables, feedback, and accessibility.
- `mysql-schema` — inspect the existing flight/airport relationships and preserve database integrity.
- `AGENTS.md` — project architecture, security, UI, and approval requirements.

## Existing code inspected

- `AGENTS.md` — project workflow, supported stack, security requirements, and MariaDB 10.11.16 schema constraints.
- `database/schema.sql` — `flights` stores `flight_number`, airport foreign keys, `departure_at`, `arrival_at`, `seat_capacity`, and `status`; validation triggers enforce different airports, arrival after departure, and positive capacity. `airports` provides IATA code, name, city, and country. Bookings reference flights with `ON DELETE RESTRICT`.
- `models/User.php` — existing model pattern using an injected PDO instance and prepared statements.
- `config/db.php` — returns exception-configured PDO with native prepares.
- `config/session.php` — secure session startup, `app_current_user()`, role validation, CSRF helpers, flash messages, and `app_escape()`.
- `login.php`, `register.php`, `includes/header.php`, `includes/navbar.php`, and `includes/footer.php` — current request-handler, shared layout, and authentication patterns.
- `assets/css/auth.css` and `ui-registry.md` — current airline navy/blue palette, white surfaces, soft borders, spacing, focus states, and UI conventions.
- `prompts/04-user-auth.md`, `prompts/05-env-database-config.md`, and `README.md` — existing feature scope and local setup guidance.
- No `views/`, `controllers/`, `Flight` model, or admin flight page currently exists.

## Decisions or assumptions

- “Flight code” maps to the existing `flights.flight_number` field.
- Origin and destination are accessible airport selectors populated from `airports`, not free-text values; persist their IDs in the existing foreign-key columns.
- Add `ticket_price DECIMAL(10,2) NULL`. Existing flight records have no known price, so leave their prices unknown rather than inventing a fare. Require a positive price when creating or updating a flight; render a legacy null price as “Price not set.”
- Keep the requested view at `views/admin/flights.php` and use a top-level `admin-flights.php` request handler to authorize requests, process POST actions, load data, and render the view. This follows the existing root-level page pattern while keeping view and request logic separate.
- Use the existing authentication session and shared CSRF/flash/escaping helpers. Redirect unauthenticated users and passengers to `login.php` with a safe notice; do not treat a submitted role as authorization.
- Deleting a flight referenced by bookings must fail safely because the current foreign key restricts deletion. Do not cascade-delete bookings or manifests.
- Apply an additive SQL migration to existing installations; updating `CREATE TABLE IF NOT EXISTS` alone does not add the new column to an already-created table. Use MariaDB 10.11.16-compatible `ADD COLUMN IF NOT EXISTS` so the schema import and migration remain safe to repeat.
- Do not add a framework, package, separate admin authentication flow, flight-status management feature, or booking/manifest functionality.

## Files likely to change

- `admin-flights.php` — admin authorization, request validation, POST/CSRF handling, redirects, and data preparation.
- `views/admin/flights.php` — accessible dashboard and forms for create/edit/delete actions.
- `models/Flight.php` — prepared-statement operations for airports and flight CRUD.
- `database/schema.sql` — include the nullable fare column in fresh installs and enforce positive fares on inserts/updates through the existing flight validation triggers.
- `database/migrations/06-flight-ticket-price.sql` — additive migration for existing databases; add the nullable field and update validation triggers without dropping existing data.
- `docs/database-erd.md` — document the fare field and its legacy-null behavior.
- `README.md` — explain the one-time migration and dashboard entry point.
- `login.php` and `includes/navbar.php` — send authenticated admins to the dashboard after login and expose an admin-only dashboard link, if needed to make the new page discoverable.
- `assets/css/auth.css` — add only directly related dashboard styling, reusing current design tokens.
- `ui-registry.md` — record the completed dashboard’s reusable visual patterns.

## Implementation requirements

- Authorize every dashboard request before connecting to the database or processing actions: call `app_start_session()`, obtain `app_current_user()`, and require the database-backed role to equal `admin`. Redirect every non-admin and exit.
- Use POST only for create, update, and delete. Validate the existing CSRF token before any mutation; use POST/Redirect/GET and existing flash helpers for feedback.
- Implement typed flight model methods consistent with the existing PHP 8.0-compatible codebase. Bind all values with PDO prepared statements; never interpolate submitted values into SQL.
- Load airport options from `airports` and flight rows with origin/destination airport information. Display a useful empty state when no airports or flights exist.
- Validate all fields server-side: non-empty flight code within the schema’s 10-character limit; valid, distinct airport IDs; valid departure/arrival datetimes with arrival later than departure; seat capacity within `SMALLINT UNSIGNED` and greater than zero; and a strictly positive decimal fare with at most two fractional digits and a value fitting `DECIMAL(10,2)`. Retain safe submitted values after validation failures and never trust client-side validation alone.
- Convert HTML `datetime-local` values to the database datetime format only after strict validation. Keep fare input as a validated decimal string to avoid floating-point rounding.
- Handle uniqueness conflicts and foreign-key delete restrictions with explicit, safe user feedback. Log unexpected database exceptions server-side without exposing SQL, credentials, or stack traces to the page. Do not use broad catches or success-shaped fallbacks.
- For deletion, verify the flight identifier and CSRF token, use a prepared delete statement, and explain when existing bookings prevent deletion. Do not delete related bookings.
- Keep view markup separate from handlers/model operations. Escape every dynamic value with `app_escape()` (or equivalent `htmlspecialchars()` flags) in text and attributes. Use semantic forms, labels, accessible validation/error feedback, and descriptive action names.
- Build a responsive Bootstrap 5 dashboard: clear page heading and primary create action, a labeled create/edit form, and a readable flight table within `.table-responsive`; stack or scroll appropriately on small screens. Reuse the established navy, blue, white, and muted-border palette, system typography, spacing, rounded controls, and visible focus treatment. Do not add unnecessary dependencies or custom JavaScript.
- Keep flight number uniqueness consistent with the existing `(flight_number, departure_at)` key.
- Update fresh-install schema and the existing-database migration consistently. Preserve legacy rows with `NULL` fare; validation triggers must reject inserts and updates without a positive fare while allowing untouched legacy null rows to remain. Do not drop/recreate the database or recommend re-importing the schema as the migration.
- Update login redirection and navigation only as needed so admins can reach the dashboard and passengers cannot see an admin action.

## Security requirements

- Enforce administrator authorization server-side on every dashboard request; do not rely on hidden links or client-side checks.
- Use PDO prepared statements for every query and parameter binding for submitted data.
- Protect every state-changing action with CSRF validation and POST; do not mutate data on GET.
- Validate all submitted values server-side and enforce airport foreign keys and schema constraints.
- Escape all dynamic HTML output with UTF-8 `htmlspecialchars()` using `ENT_QUOTES | ENT_SUBSTITUTE`.
- Preserve secure session behavior and rely exclusively on the database-backed role stored by the existing authentication flow.
- Do not expose SQL/PDO details, stack traces, credentials, or submitted unsafe markup in responses or logs.
- Preserve booking and passenger-manifest records; rely on the current restrictive foreign key rather than destructive cascades.

## Acceptance criteria

- Guests and passengers cannot view or mutate the dashboard and are redirected to `login.php`; authenticated admins can use it.
- Admins can create, read, update, and delete flights with all requested fields, using airport options from the existing `airports` table.
- Invalid values, missing/invalid CSRF tokens, duplicate flight code/departure combinations, and booking-restricted deletes are rejected with safe feedback and no unintended mutation.
- New and updated flights have a positive fare. Existing records remain intact with a null fare until an admin updates them, and the UI clearly identifies those fares as unset.
- SQL and HTML output are safe; no mutations occur through GET; successful mutations redirect to prevent accidental form resubmission.
- The database schema supports both a fresh installation and an additive migration of an existing installation without deleting data.
- The dashboard is usable with keyboard and screen-reader navigation and responds at mobile, tablet, and desktop widths using Bootstrap 5.
- Existing passenger registration/login/logout behavior remains intact, and an admin can reach the dashboard after signing in.
- Directly related documentation and `ui-registry.md` describe the resulting behavior and design patterns.

## Checks to run

- Run PHP syntax checks on every new or changed PHP file using the available PHP runtime.
- Validate the migration and schema trigger definitions against the project’s MariaDB 10.11.16 target without destructive database operations.
- If the local database is available, check PDO connectivity and verify the `flights.ticket_price` column and its triggers after the one-time migration; do not print credentials.
- Exercise model/input validation for valid and invalid airport IDs, datetime ordering, seat-capacity bounds, fare precision/range, uniqueness conflicts, and booking-restricted deletes using the project’s available test approach. Do not introduce a testing framework solely for this feature.
- Inspect Apache/PHP logs for warnings or errors during manual checks, without exposing credentials.
- Review every dashboard query for prepared statements and every dynamic output for escaping.

## Exact manual test steps expected after implementation

1. Confirm Apache and MariaDB are running and the project’s `.env` database settings are configured.
2. In phpMyAdmin, select the existing project database and apply `database/migrations/06-flight-ticket-price.sql`. Confirm existing flights are still present and their `ticket_price` values are `NULL`. For a new database, import the updated `database/schema.sql` instead.
3. Ensure at least two airports exist in `airports`; use the project’s established local setup method if airport fixtures are needed.
4. Open `http://localhost/airline-reservation-system/admin-flights.php` as a guest. Confirm it redirects to `login.php`.
5. Sign in as a passenger, revisit the dashboard URL, and confirm the passenger is redirected and receives a safe access notice.
6. Sign in as an admin. Confirm successful admin login opens the dashboard and the admin navigation link is visible.
7. Submit the create form with valid values and confirm the flight appears with its code, airport route, schedule, capacity, and two-decimal fare.
8. Try a missing CSRF token, same origin and destination, invalid/reversed times, out-of-range seats, zero/invalid fare, and a duplicate code with the same departure time. Confirm each is rejected with a useful message and no invalid record is created.
9. Edit an existing flight and verify the changed values persist. Confirm a legacy `NULL` fare is shown as unset and must be supplied with a positive value when updating that flight.
10. Create a booking for a flight using the existing project workflow or a controlled local database fixture; attempt to delete the referenced flight and confirm the booking is preserved and deletion is refused with clear feedback.
11. Delete an unbooked flight and confirm it is removed after redirect. Confirm reloading the resulting page does not repeat the mutation.
12. Check the dashboard at desktop and mobile viewport widths, keyboard through controls and action forms, and confirm the table remains usable via responsive scrolling.
13. Review the Apache/PHP error log after the checks and confirm there are no warnings, undefined variables, or uncaught failures.
