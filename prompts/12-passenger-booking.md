# Passenger Booking, Seat Assignment, and Ticket Generation

## Goal

Upgrade the existing passenger reservation form into a three-step flow: enter passenger details, choose a specific seat for each traveler, and review/confirm the reservation. On final confirmation, atomically insert the booking, passenger manifest, and seat assignments, so the flight's remaining availability decreases. Generate an owner-protected printable ticket page and a downloadable PDF ticket from the saved reservation.

## Skills read

- `.agents/skills/php-pro` — PDO transactions, server-side validation, session security, and PHP conventions.
- `.agents/skills/architect` — preserve the existing vanilla PHP/custom MVC separation and resolve design decisions before implementation.
- `.agents/skills/mysql-schema` — inspect database relationships and plan additive, MariaDB-compatible schema changes. The skill's required `.agents/skills/mysql-shared/SKILL.md` reference is not present in this workspace.
- `.agents/skills/bootstrap-components` — responsive Bootstrap 5 forms, selectable seat controls, progress indicators, and accessible feedback.

## Existing code inspected

- `AGENTS.md` — prompt approval workflow, architecture, security, database compatibility, and verification requirements.
- `prompts/11-upcoming-flights-and-booking.md` — existing booking behavior and reservation/payment scope.
- `book-flight.php` — current single-page passenger-name form, CSRF/session checks, pending confirmation state, and POST/redirect/GET behavior.
- `models/Booking.php` — current flight availability lookup and transactionally created pending bookings/manifests with a flight-row lock.
- `models/Flight.php` — flight data and existing seat-availability calculation based on pending/confirmed bookings and manifest counts.
- `database/schema.sql` and `docs/database-erd.md` — current `bookings` and `passengers_manifest` schema and MariaDB 10.11.16 trigger compatibility notes.
- `config/db.php` and `config/session.php` — PDO configuration, passenger/admin session identity, CSRF helpers, and HTML escaping.
- `includes/header.php`, `views/flight-card.php`, and `assets/css/flight-search.css` — Bootstrap 5.3.3, shared layout, existing booking entry point, colors, and responsive styles.
- `README.md` — local XAMPP/MariaDB setup and current feature limitations.
- No project test suite was found during inspection.

## Decisions and assumptions

- Seat assignment is a passenger-facing seat map: the passenger selects one available numbered seat for each traveler.
- Provide both a printable ticket page and a downloadable PDF ticket. The PDF must be a real PDF download, not only instructions to use the browser's “Save as PDF” feature.
- Treat the final “Confirm reservation” action as booking confirmation in the flow, but retain the application's existing `pending` booking status. Do not imply payment, paid status, or boarding authorization; payment is not part of this feature.
- Do not add a separate mutable available-seat counter to `flights`. Remaining seats are derived from capacity and active bookings/assignments so they cannot drift from the reservation records.
- Unless inspection finds existing aircraft/seat-layout data, represent capacity as six seats per row labelled A–F (for example, `1A`–`1F`), with the final row limited to the configured capacity. Clearly show this convention in the seat-selection UI.
- Keep existing pending/confirmed occupancy semantics and account/flight ownership rules. Existing reservations without stored seat assignments must still consume capacity and must not make the seat map appear to have more selectable seats than are actually available.
- Inspect available PHP dependencies before choosing PDF generation. Reuse an existing library if present; if none exists, add and document the smallest suitable maintained Composer dependency rather than returning HTML as a PDF.
- Inspect the live target database engine/version before schema import or migration. The README records MariaDB 10.11.16 as a previously observed local environment, not a guarantee about the currently running server.

## Files likely to change

- `prompts/12-passenger-booking.md` — this approved implementation specification.
- `book-flight.php` — three-step flow, step validation, review, final confirmation, and confirmation/ticket links.
- `models/Booking.php` — seat availability and assignment reads; transactional booking, manifest, and assignment writes.
- `models/Flight.php` — consistent remaining-seat/seat-map availability queries if not adequately covered by the booking model.
- `database/schema.sql` and a new additive migration under `database/migrations/` — persistent seat assignment structure, if required by inspection.
- `docs/database-erd.md` and `README.md` — seat relationship, migration/setup, ticket behavior, and accurate feature limitations.
- `ticket.php` and a PDF download handler or equivalent narrowly scoped route — owner-authorized ticket rendering/download from persisted data.
- `assets/css/flight-search.css` and, if needed, a small file under `assets/js/` — responsive seat map, step indicator, and print behavior.
- `composer.json` / `composer.lock` only if no suitable PDF library is already available.
- Focused test files or scripts, if the repository has or can support a suitable test setup without unrelated tooling changes.

## Implementation requirements

### Three-step reservation flow

1. **Passenger details:** retain the current one-to-six traveler limit and collect/validate first and last names for each traveler.
2. **Seat assignment:** render the selected flight's seat map; require exactly one available, unique seat per traveler. Allow passengers to move between steps without silently losing valid form values.
3. **Review and confirmation:** show route, flight number, schedule, passenger names, each seat, fare per traveler, total fare, remaining availability, and a final explicit confirmation action.

- Keep the selected flight identity validated server-side on every step. Re-fetch the flight and availability rather than trusting posted fare, seat status, itinerary, or totals.
- Use server-side validation for all step transitions and final submission; client-side JavaScript may improve interaction but must not be authoritative.
- After successful confirmation, use POST/redirect/GET and show the saved booking reference, pending status, assigned seats, and both ticket actions.
- If seats become unavailable between display and confirmation, reject the submission without a partial booking, report the conflict, and let the passenger reselect from refreshed availability.

### Seat persistence, availability, and transaction

- Inspect the live schema and existing data before designing the smallest additive migration needed to persist unique seat assignments per flight and traveler. Preserve existing rows, relationships, and reservation history; do not drop/recreate tables or introduce destructive setup instructions.
- Ensure existing pending/confirmed reservations without assignments still occupy seats in both the remaining-seat count and the seat map. Backfill deterministically or account for these reservations as unavailable without allowing collisions with newly assigned seats.
- On final confirmation, use one PDO InnoDB transaction:
  1. Lock the selected flight row (`SELECT ... FOR UPDATE`) and verify it is future, scheduled, priced, and valid.
  2. Recalculate active occupancy and revalidate every selected seat against current availability and capacity.
  3. Insert one `bookings` row with a cryptographically random opaque reference and the existing `pending` status.
  4. Insert one manifest row per traveler and persist each selected seat against the corresponding traveler/booking.
  5. Commit only after every write succeeds; roll back on every exception.
- Use prepared statements for every query and write. Enforce per-flight seat uniqueness at the database level where supported, in addition to transaction-time checks. Handle duplicate-seat/concurrent booking conflicts with a safe retry/reselection message.
- Compute remaining seats from capacity minus active occupancy/assignments. Do not decrement or overwrite `seat_capacity`; ensure all existing flight search and booking availability displays continue to report the same correct count.
- Validate the database engine/version before applying DDL. Keep the migration compatible with the confirmed target (currently documented as MariaDB 10.11.16) and verify its tables, keys, and foreign keys. Do not restore unsupported `CHECK` constraints or recommend destructive database resets.

### Printable and downloadable ticket

- Build a printable ticket/itinerary page and a real downloadable PDF for the saved reservation. Include booking reference, reservation status, flight/route, departure and arrival, passenger names, assigned seats, and fare summary as appropriate.
- Load ticket data from persisted records using the opaque booking reference; authorize the owner passenger and reject admin/other-account access. Do not trust client-provided ticket content or sequential database IDs as public credentials.
- Escape all HTML output and safely encode values in the PDF. Make the PDF response use a correct `application/pdf` content type and safe attachment filename.
- Keep the ticket's wording consistent with the `pending` reservation: no claim that payment was collected, a ticket is paid/confirmed, or the document is a boarding pass.
- If PDF generation fails after the database transaction commits, keep the reservation intact, log the failure without exposing details, and show a safe way to retry/download later.
- Ensure the ticket page has print-specific styling that omits navigation and controls, and remains readable on screen and paper.

### Visual and accessibility requirements

- Reuse the current dark navbar, violet action color, rounded white panels, Bootstrap 5 grid, and typography/spacing patterns from the flight-search UI.
- Show clear step labels and current-step state. Use a responsive seat grid with textual labels and distinct selected, available, and unavailable states; do not encode state by color alone.
- Make seat controls keyboard-operable and screen-reader understandable, with visible focus, labels, and an announced selection count. Avoid requiring drag-and-drop or mouse-only interaction.
- Associate form errors and instructions with controls, use semantic headings and accessible alert regions, and preserve entered values after validation failures.
- Stack cleanly on mobile without horizontal overflow. Ensure ticket content and print stylesheet remain usable without color.

## Security requirements

- Require an authenticated passenger for booking; reject guests and admin roles from reservation submission. Preserve the selected flight through the existing validated internal sign-in/registration return flow.
- Apply `app_csrf_token()` / `app_is_valid_csrf_token()` to every state-changing POST, and validate step state, flight IDs, traveler counts/names, seats, and booking ownership server-side.
- Use PDO prepared statements, transaction rollback, secure session behavior, cryptographic booking references, and `app_escape()` (or equivalent `htmlspecialchars()`) for dynamic HTML.
- Do not expose SQL/PDO errors, credentials, or filesystem paths to users. Log server-side failures and display safe, actionable messages.
- Do not collect payment or card details, add arbitrary redirects, or make ticket routes accessible through guessable sequential IDs alone.

## Acceptance criteria

- A passenger completes separate details, seat selection, and review/confirmation steps, with validation and preserved input on errors.
- Exactly one distinct available seat is persisted for every traveler; seat assignments are unique per flight and linked to the correct booking and manifest traveler.
- Final confirmation inserts the `bookings` record, manifest rows, and seat assignments atomically in a PDO transaction. Any failure leaves no partial booking data.
- Confirmed reservations reduce computed availability immediately; stale seat choices, concurrent attempts, and requests exceeding remaining capacity cannot oversell a flight.
- Existing bookings without a seat assignment still consume capacity and are represented as unavailable in seat selection.
- Search results and the booking page continue to show correct available seat counts; flight capacity itself is not corrupted to represent remaining seats.
- Owner passengers can print and download a valid PDF ticket containing persisted passenger/seat/flight data; other users and admin accounts cannot access it.
- The ticket clearly shows the reservation reference and pending status and does not imply payment or boarding authorization.
- Existing database data is preserved by the additive migration, and schema documentation matches the validated target engine.
- No unrelated booking history, payment integration, cancellation workflow, or feature expansion is introduced.

## Checks to run

- Identify the live database engine/version before applying migration SQL; validate the migration and verify the resulting schema, foreign keys, indexes, and seat uniqueness mechanism.
- Run `php -l` on all changed PHP files; run the project's tests if added/available. If a PDF dependency is added, verify Composer dependency resolution and exercise the PDF response.
- Verify PDO connectivity without printing credentials. Exercise transactional commit and rollback, capacity boundaries, unique seat selection, stale-seat conflict, and concurrent reservation behavior against a dedicated test database or isolated fixtures; do not delete or reseed the developer's configured data.
- Confirm the PDF opens as a valid document with correct headers and the print page renders without navbar/buttons.
- Check Apache/PHP logs with `error_reporting(E_ALL)` for warnings, deprecated behavior, and undefined variables.
- Review the diff for scope, escaped output, prepared statements, owner authorization, and preservation of existing booking/search behavior.

## Manual test steps

1. Start Apache and MariaDB in XAMPP. Confirm the live database engine/version in phpMyAdmin before applying the additive migration; retain existing records.
2. Sign in as a passenger, choose a future scheduled flight with known availability, and begin a booking for one traveler.
3. Enter valid passenger details, continue to the seat map, and select one available seat using only the keyboard. Confirm the selection and proceed.
4. On the review step, verify flight, passenger, seat, fare, and total; submit the final confirmation once.
5. Verify the page shows a unique booking reference, `pending` status, selected seat, and print/download actions. In phpMyAdmin, verify exactly one booking, one manifest row, and one corresponding seat assignment were committed.
6. Reload/search the same flight and verify available seats decreased by one and the assigned seat is unavailable.
7. Repeat with multiple travelers; confirm each gets a different seat and the fare total matches the traveler count.
8. Open the printable ticket and print preview; confirm navigation/buttons are omitted from print. Download the PDF and verify it opens and contains the persisted reservation details.
9. Try submitting a stale/already-selected seat, duplicate seats, invalid traveler data, invalid flight ID, and a missing/invalid CSRF token. Confirm safe errors and no partial booking records.
10. Simulate an insufficient-capacity or write failure using isolated test fixtures and verify the transaction rolls back without decreasing availability incorrectly.
11. Test a guest and an administrator account; neither may submit a passenger booking. Try opening another passenger's ticket reference and confirm access is denied.
12. Confirm existing search results and old bookings still calculate occupancy correctly, including legacy bookings that predate seat assignments.
