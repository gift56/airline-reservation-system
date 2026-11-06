# Implementation Prompt: Admin Airport Entry and Working Flight Actions

## Goal

Fix the admin dashboard when the `airports` table is empty, without seed data. Let administrators add airport records manually, then create flights using those airports. Make the “Add airport” and “Add a flight” actions open usable Bootstrap forms rather than changing the URL hash. Make the navbar’s “Manage flights” link target the dashboard’s flight list.

## Skills read

- `php-pro` — vanilla PHP/PDO conventions, validation, and safe request handling.
- `bootstrap-components` — accessible Bootstrap modal behavior and form patterns.
- `AGENTS.md` — project architecture, admin security, and prompt-before-implementation workflow.

## Existing code inspected

- `AGENTS.md` — application structure, security, UI, and implementation workflow.
- `database/schema.sql` — the existing `airports` table has unique `iata_code CHAR(3)`, `name VARCHAR(150)`, `city VARCHAR(100)`, and `country VARCHAR(100)`. Flights reference airport IDs.
- `models/Flight.php` — reads airport options and currently supports paginated flight CRUD; no airport-create operation exists.
- `admin-flights.php` — verifies the admin session, processes CSRF-protected POSTs, loads airports, and renders dashboard data.
- `views/admin/flights.php` — the flight create form is omitted when no airports exist and replaced by an informational message. “Add a flight” is currently a hash anchor, not a modal trigger.
- `includes/navbar.php` — the admin “Manage flights” item currently points back to the same dashboard route without a flight-list fragment.
- `includes/header.php` and `includes/footer.php` — Bootstrap 5.3 CSS and bundle are already loaded for pages.
- `config/session.php` — existing CSRF, flash-message, role, and escaping helpers.
- `assets/css/auth.css` and `ui-registry.md` — current dashboard palette and component conventions.
- The previous screenshot shows the zero-airport dashboard state and the missing flight form fields.

## Decisions or assumptions

- Add only airport creation; do not build airport update/delete, airport search, or bulk import.
- Do not add seed data, run migrations, or insert records automatically. The administrator manually enters airport details in a dashboard modal.
- Add airport fields for IATA code, airport name, city, and country. Normalize the IATA code to uppercase and enforce schema lengths and uniqueness server-side.
- Use Bootstrap modals for both “Add airport” and “Add a flight,” because the existing page uses Bootstrap 5 and the current flight link only changes the fragment.
- If there are no airports, keep the flight modal action usable but show a clear explanation and a button opening the airport modal. After creating an airport, the admin can reopen the flight modal; do not create a flight automatically.
- Retain the current edit flow, pagination, CSRF, PRG behavior, and form validation. Reopen the relevant modal after validation failure so errors and submitted values remain actionable.
- Point the navbar item to `admin-flights.php#flight-list`; it will reach the list when clicked from another page and focus the correct dashboard section.

## Files likely to change

- `models/Flight.php` — add a prepared-statement airport insertion method.
- `admin-flights.php` — handle the `create_airport` POST action, validate fields, map duplicate-code errors, reload airports after validation failures, and select which modal should reopen.
- `views/admin/flights.php` — add airport and flight modal forms and triggers; replace the current hash-only Add a flight anchor; retain the existing editing, table, and pagination UI.
- `includes/navbar.php` — target the dashboard flight-list anchor.
- `assets/css/auth.css` — add only necessary modal-specific styling, using existing tokens.
- `README.md` — explain manual airport setup before flight creation; make clear no seed is required.
- `ui-registry.md` — record modal/airport form patterns if they introduce reusable visual rules.

## Implementation requirements

- All airport and flight mutation requests remain behind the existing admin role check and use POST with the existing CSRF token. Never trust a request role.
- Add `Flight::createAirport()` or a clearly named equivalent using a prepared `INSERT`. Do not interpolate user data in SQL.
- For airport creation, trim/normalize inputs; validate a three-letter IATA code, non-empty name/city/country, and schema character limits (150/100/100); report duplicate IATA codes safely.
- After successful airport creation, set an escaped-safe flash message and redirect to the dashboard at the current page. Do not insert or edit a flight as a side effect.
- On validation failure, retain escaped airport input and reopen the airport modal. On flight create/update validation failure, retain current behavior and reopen the flight modal.
- Keep the flight create/update inputs accessible in the modal even when the airport list is empty; show instructions and an airport-modal trigger rather than replacing/hiding the flight action.
- After an airport exists, populate the flight modal origin/destination selectors from the database, and leave the manual flight form available without any demo seed.
- The flight modal must include flight code, origin, destination, departure, arrival, seats, and ticket price. Preserve the existing edit workflow, or open the same modal populated for edit without breaking pagination.
- Add stable element IDs for modal triggers/targets and the flight list. Use Bootstrap data attributes, `aria-labelledby`, a labeled close button, associated form labels, and normal dialog semantics. The bundle is already loaded by `includes/footer.php`.
- After an invalid form POST, emit any modal-opening JavaScript only after the Bootstrap bundle is available; use the existing server-rendered error/flash patterns and do not add a dependency.
- Set POST form actions to the correct root-level handler (`admin-flights.php`) even though the view is nested in `views/admin/`.
- Change the navbar link to the dashboard list anchor. Do not create a redundant route or make it trigger a destructive action.
- Keep all dynamic output escaped with `app_escape()`. Preserve database error details only in server logs, not responses. Handle unique constraint conflicts explicitly.
- Update README with manual instructions: add two or more airports from the dashboard, then add a flight. State that Nigerian seed data remains optional.
- Keep the existing airport and flight records untouched; do not alter the schema.

## Security requirements

- Check administrator authorization before opening the database or processing airport/flight actions.
- Require CSRF validation for airport creation as for all other state changes.
- Use prepared PDO statements for airport inserts and existing flight queries/mutations.
- Validate all fields on the server and rely on the unique key as the final duplicate guard.
- Escape airport values in modals, alerts, and options. Do not render raw request values.
- Do not perform mutations on GET or automatically seed airports/flights.

## Acceptance criteria

- With an empty `airports` table, an admin sees a working “Add airport” action and can create an airport without importing seed SQL.
- With no airports, the “Add a flight” button opens a modal that explains the prerequisite and provides a working route to the airport form.
- After two or more airports are manually created, the flight modal presents those airport options and an admin can create a valid flight.
- Both Add buttons open dialogs without only changing the URL hash; forms submit to the correct PHP handler and validation errors reopen the corresponding dialog with entered values retained.
- Duplicate or invalid airport input is rejected with clear safe feedback; invalid/missing CSRF tokens do not insert data.
- Existing flight edit/delete, pagination, role protection, and validation continue to work.
- The navbar “Manage flights” link navigates to the flight list section and remains admin-only.
- No seed SQL or database migration is executed or required by this feature.

## Checks to run

- Run PHP syntax checks on changed PHP files using `C:\xampp\php\php.exe`.
- Run `git diff --check`.
- Test airport validation for uppercase normalization, invalid IATA code, empty/overlong fields, duplicate code, and invalid CSRF.
- Test modal markup IDs/targets, correct form actions, zero-airport and populated-airport rendering, and server-side validation-error modal reopening.
- If XAMPP services are available, verify PDO connection and manually exercise airport creation followed by flight creation; do not seed data.
- Check Apache/PHP logs for warnings, notices, undefined variables, and database errors.
- Confirm guest and passenger requests remain redirected from the dashboard.

## Exact manual test steps expected after implementation

1. Start Apache and MariaDB in XAMPP, then open the project database in phpMyAdmin. Do not import seed data.
2. Confirm `airports` is empty (or use an empty test database), then sign in as an admin and open `http://localhost/airline-reservation-system/admin-flights.php`.
3. Click “Add airport” and confirm a modal opens. Enter a Nigerian airport, for example IATA code `LOS`, airport name `Murtala Muhammed International Airport`, city `Lagos`, and country `Nigeria`; save and confirm the success notice.
4. Repeat for a second airport, for example `ABV`, `Nnamdi Azikiwe International Airport`, `Abuja`, `Nigeria`.
5. Click “Add a flight” and confirm the modal opens with LOS and ABV available as origin/destination. Enter valid flight code, times, seats, and a positive ticket price; save and confirm it appears in the list.
6. Submit an invalid/duplicate airport and a missing-CSRF airport request; confirm clear errors and no bad/duplicate row is created.
7. Submit an invalid flight and confirm the flight modal reopens with values preserved.
8. Use Edit from a paginated flight list and confirm the existing edit behavior and return page remain intact.
9. From another page, click “Manage flights” in the admin navbar and confirm the browser navigates to the dashboard flight list.
10. Visit the dashboard as a guest and passenger; confirm both remain redirected to login.
