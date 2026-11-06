# Implementation Prompt: Admin Airport Editing and Deletion

## Goal

Extend the admin flight dashboard's airport setup area so administrators can edit and delete airports in addition to adding them. When deleting an airport that is referenced by flights, delete those flights in the same transaction only if none of them has bookings. Preserve reservation history by refusing deletion if any affected flight has a booking.

## Skills read

- `php-pro` — vanilla PHP/PDO conventions, validation, transaction-safe request handling, and secure database operations.
- `architect` — model/controller/view separation and alignment on data-integrity decisions.
- `bootstrap-components` — accessible Bootstrap buttons, forms, dialogs, and confirmation patterns.
- `AGENTS.md` — project architecture, admin security, and prompt-before-implementation workflow.

## Existing code inspected

- `AGENTS.md` — architecture, security, database compatibility, and implementation workflow.
- `database/schema.sql` — airports have a unique IATA code; flights reference airports with `ON DELETE RESTRICT`; bookings reference flights with `ON DELETE RESTRICT`.
- `models/Flight.php` — airport listing and creation, flight CRUD, and PDO usage.
- `admin-flights.php` — administrator authorization, POST action routing, CSRF validation, airport loading, flash messages, and dashboard error handling.
- `views/admin/flights.php` — airport list and Add Airport modal, flight modal, flight CRUD controls, and Bootstrap bundle-dependent modal behavior.
- `config/session.php` — session, CSRF, flash, and escaping helpers.
- `README.md` — airport setup and existing documented restriction on airport editing/deletion.
- `prompts/08-admin-airport-entry-and-action-modals.md` — approved behavior and implementation context for airport creation.

## Decisions or assumptions

- Airport edit covers all four fields: IATA code, airport name, city, and country.
- IATA codes remain uppercase, exactly three letters, and unique. Updating an airport's ID is not supported.
- Airport deletion is allowed when the airport has related flights, but those flights must be deleted as part of the same transaction.
- If any related flight has a booking, refuse the entire airport deletion and preserve all related flights and bookings. Do not cascade into or delete booking records.
- Keep deletion as an explicit admin action requiring POST and CSRF; show a browser confirmation before submitting.
- No migration, seed import, or unrelated flight-dashboard redesign is needed.

## Files likely to change

- `models/Flight.php` — add prepared-statement airport update and safe deletion operations, including transaction and booking-reference checks as appropriate to the existing model boundary.
- `admin-flights.php` — process airport update/delete actions, validate submitted IDs and field values, map duplicate IATA codes and referenced-booking cases to clear messages, and preserve submitted edit values on errors.
- `views/admin/flights.php` — add Edit and Delete controls for each airport and a prefilled airport-edit modal or equivalent accessible form; include an explicit deletion confirmation.
- `README.md` — document editing/deleting airports, related-flight deletion, and the booking-preservation rule.
- `ui-registry.md` — update only if the added airport actions introduce reusable UI patterns not already documented.

## Implementation requirements

- Keep all airport management behind the existing administrator role check.
- Handle update and delete only through POST actions protected by the existing CSRF token. Never perform mutations on GET.
- Use PDO prepared statements for every query using request values.
- Validate the airport ID as a positive integer and validate IATA code, name, city, and country using the existing schema lengths and current airport-creation rules.
- Normalize IATA codes to uppercase and safely reject duplicates, including collisions with another airport while allowing an unchanged code for the edited airport.
- Preserve edit input and reopen the edit form after validation or uniqueness errors.
- On delete, execute the related-flight check and deletion atomically in a transaction. If a related flight has any booking, roll back and retain all data. Otherwise, delete the related flights and airport within the transaction. Let database foreign keys remain the final integrity guard and report unexpected failures without exposing SQL details.
- Handle concurrent booking/deletion safely: do not report success or leave partial deletion if a constraint or transaction operation fails.
- Use the project's existing flash/redirect patterns for success and safe error messages.
- Escape all airport values and messages rendered by the view using `app_escape()`.
- Preserve existing flight CRUD, pagination, airport creation, and modal behavior.
- Avoid adding schema changes or changing the existing foreign-key deletion rules.

## Security requirements

- Check administrator authorization before opening the database or processing airport actions.
- Require a valid CSRF token for airport updates and deletions.
- Bind all request-derived query values using PDO prepared statements.
- Enforce server-side validation regardless of client-side constraints.
- Escape dynamic output and do not expose SQL exceptions or database internals to users.
- Do not delete booking records or execute airport/flight mutations from GET requests.

## UI interpretation and accessibility

- Keep the airport list inside the existing Airport setup card and follow its typography, spacing, colors, and Bootstrap 5 button patterns.
- Provide clearly labeled Edit and Delete actions per airport; keep them usable at narrow viewport widths by allowing controls to wrap.
- Use a labeled, keyboard-accessible Bootstrap modal or equivalent for airport editing, with associated form labels, modal heading, close control, and validation feedback.
- Use an explicit confirmation step for deletion and clearly state that related flights will also be removed if the airport is unused by bookings.
- Show an understandable error when deletion is blocked because reservation history exists; do not imply that anything was deleted.

## Acceptance criteria

- An administrator can open an airport's edit form, change any of its four fields, and save valid changes.
- Invalid values and duplicate IATA codes are rejected with safe feedback; a failed edit preserves submitted values.
- An administrator can delete an airport with no related flights.
- An administrator can delete an airport with related flights when none of those flights has a booking; all related flights and the airport are removed atomically.
- If any related flight has a booking, the delete attempt is refused and all airport, flight, and booking records remain unchanged.
- Guest and non-admin users cannot update or delete airports; invalid/missing CSRF tokens do not mutate data.
- Existing airport creation, flight management, pagination, and admin access protection continue to work.
- No seed data or migration is required.

## Checks to run

- Run PHP syntax checks on changed PHP files.
- Run existing targeted tests, or focused model/request tests for airport update, unreferenced deletion, deletion with related flights, booking-protected deletion, uniqueness, and CSRF/authorization behavior.
- Run `git diff --check`.
- Verify the PDO connection using the existing local configuration without modifying data.
- Inspect available PHP/Apache error logs for warnings, notices, or fatal errors related to the change.

## Manual test steps

1. Sign in as an administrator and open **Manage flights**.
2. Add an airport, then use its **Edit** action to change its name, city, country, and IATA code; confirm the updated values appear and the airport remains available in flight selectors.
3. Try an invalid IATA code and a code already used by another airport; confirm each is rejected and the edit form retains its values.
4. Delete an airport that is not used by a flight; confirm the browser prompt, then verify the airport disappears.
5. Create a flight using an airport, then delete that airport; verify the airport and related flight are removed.
6. Create a booking for a flight using an airport, then attempt to delete that airport; verify deletion is refused and the airport, flight, and booking remain intact.
7. Attempt airport update and delete requests without a valid CSRF token, and visit as a guest/passenger; verify no data changes and protected access is denied.
8. Re-test airport creation, flight create/edit/delete, and flight pagination to confirm no regressions.
