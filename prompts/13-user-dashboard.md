# User Dashboard and My Bookings

## Goal

Build a modern, passenger-only “My Bookings” dashboard where signed-in passengers can review their upcoming active reservations, open a styled itinerary ticket to print or download as PDF, and cancel an eligible upcoming booking. Add a visible “My bookings” route to the navbar for passenger accounts.

## Skills read

- `.agents/skills/php-pro` — existing PHP/PDO conventions, validation, and safe session-backed request handling.
- `.agents/skills/architect` — preserve the project's vanilla PHP/custom MVC separation and resolve implementation decisions before coding.
- `.agents/skills/bootstrap-components` — responsive Bootstrap 5 cards, navbar links, forms, and accessible interaction patterns.
- `.agents/skills/imprint` — capture the completed dashboard’s visual patterns in `ui-registry.md` after implementation.

## Existing code inspected

- `AGENTS.md` — prompt approval workflow, architecture, passenger authorization, security, and verification requirements.
- `prompts/12-passenger-booking.md` — booking statuses, passenger ownership, seat assignment, and ticket behavior.
- `models/Booking.php` — prepared-statement booking queries, `findTicketForPassenger()`, active occupancy rules, and seat assignment persistence.
- `book-flight.php` — existing passenger-only booking flow, POST/redirect/GET behavior, and CSRF validation.
- `ticket.php` and `ticket-download.php` — owner-protected printable ticket page and FPDF download endpoint.
- `includes/navbar.php`, `includes/header.php`, and `includes/footer.php` — current role-aware navigation and shared Bootstrap 5.3.3 layout.
- `config/session.php` — passenger identity, session checks, CSRF token validation, flash messages, and HTML escaping helpers.
- `database/schema.sql` and `database/migrations/07-booking-seats-and-fare-snapshot.sql` — `pending`, `confirmed`, and `cancelled` booking statuses; manifest and seat-assignment relationships; unique `(flight_id, seat_label)` seat index.
- `assets/css/flight-search.css` and `ui-registry.md` — current violet/charcoal airline visual language, responsive layout, focus treatment, and ticket print styling.
- `composer.json` — PHP >= 8.0 and existing FPDF dependency.

## Decisions and assumptions

- “Active” means a `pending` or `confirmed` booking whose flight departure is still in the future. Exclude canceled bookings and bookings for flights that have already departed.
- A passenger may cancel only their own active booking before departure. Use the existing `cancelled` status; retain the booking and passenger manifest records.
- `booking_seat_assignments` has a unique `(flight_id, seat_label)` index that also applies to canceled rows. To release seats for reuse without a schema migration, delete only the canceled booking’s seat-assignment rows in the same transaction as the status change. The existing ticket route can still show the canceled status, but those released seats will no longer be present in its traveler list.
- “Ticket” actions reuse the existing owner-authorized `ticket.php?ref=...` and `ticket-download.php?ref=...` routes rather than duplicating ticket generation or adding another PDF dependency.
- Guests are redirected to sign in with a safe internal return path; admin accounts receive an authorization failure and do not see passenger booking controls.
- No payment, booking-history archive, schema migration, or unrelated account features are in scope.

## Files likely to change

- `prompts/13-user-dashboard.md` — this approved implementation specification.
- `my-bookings.php` — passenger authorization, booking list retrieval, cancellation POST handling, and dashboard rendering.
- `models/Booking.php` — passenger-owned upcoming booking listing and atomic cancellation operation.
- `includes/navbar.php` — passenger-only “My bookings” navigation route.
- `assets/css/flight-search.css` — dashboard layout, booking/ticket cards, status presentation, and responsive refinements, reusing current tokens and patterns.
- `ui-registry.md` — record the completed dashboard’s visual patterns after implementation.
- Focused test files or scripts, if an existing-compatible test setup is available without adding unrelated tooling.

Do not change the database schema unless implementation reveals an unavoidable compatibility issue; if so, stop and request approval for the scope change before writing migration SQL.

## Implementation requirements

### Passenger dashboard and navigation

- Add a `my-bookings.php` page using the existing shared header/navbar/footer.
- Require a valid logged-in passenger identity before querying or rendering bookings. Redirect guests to login using the project's validated internal return flow; reject administrators with HTTP 403.
- Add a “My bookings” navbar link only for passengers. Preserve the existing guest and administrator navigation behavior.
- Query only the current passenger’s upcoming bookings in `pending` or `confirmed` status. Include the booking reference, status, flight number, origin/destination, departure/arrival, traveler count and names, seat labels when available, and saved fare snapshot where available. Do not expose another passenger’s booking data.
- Render a useful empty state with a clear link back to flight search when the passenger has no upcoming active bookings.
- Show an explicit confirmation prompt before submitting a cancellation request. The server must remain authoritative regardless of client-side confirmation.
- After successful cancellation, use POST/redirect/GET and show a safe success flash message. On failure, retain the booking and display a safe, actionable error.

### Ticket actions

- Provide a “View / print ticket” action that opens the existing owner-protected `ticket.php` page and a “Download PDF” action that opens `ticket-download.php`, both using the opaque booking reference.
- Keep ticket content sourced from saved booking, flight, manifest, and seat-assignment records. Do not build ticket contents from query parameters or dashboard form values.
- Preserve the existing ticket disclaimer and status wording. Do not imply payment, paid/confirmed ticket status, or boarding authorization.

### Cancellation and seat release

- Accept cancellation only through POST with a valid `app_csrf_token()` / `app_is_valid_csrf_token()` token. Validate the reference as a string and use it with the authenticated passenger ID; do not accept a sequential booking ID as authorization.
- In the model, use prepared statements and a transaction to lock and re-check the owned booking and its flight departure/status. Cancel only if the booking is still `pending` or `confirmed` and departure remains in the future.
- Update status to the existing `cancelled` value and delete only that booking’s seat-assignment rows in the same transaction, freeing its unique flight/seat labels. Keep the booking and passenger manifest records. Roll back on any exception and rethrow/report errors according to existing project patterns.
- Do not allow cancellation of another passenger’s booking, a canceled booking, or a booking whose flight has departed. A failed or stale cancellation must not release seats.
- Existing seat-availability queries count only `pending` and `confirmed` bookings. Verify that a canceled booking is therefore excluded from occupancy and its released seat can be booked again.

### Visual and accessibility requirements

- Reuse the dark charcoal navbar, violet primary action, soft neutral canvas, rounded white surfaces, restrained borders, and system typography documented in `ui-registry.md`.
- Create a clear dashboard heading and brief welcome line, followed by a responsive stack/grid of booking cards with route, schedule, status, booking reference, passenger/seat summary, and actions.
- Use Bootstrap 5 responsive grid/card utilities; collapse card details and actions cleanly on mobile without horizontal overflow.
- Make status understandable through text labels as well as color. Use semantic headings, visible focus states, labeled actions, and accessible status/feedback regions.
- Keep cancellation forms as real forms with an accessible submit control and CSRF token. Any JavaScript confirmation must not be required for server-side protection.
- Ensure ticket actions remain usable with keyboard and assistive technology; preserve existing print layout and hide page navigation/actions during print.

## Security requirements

- Enforce passenger role authorization on every dashboard request and cancellation submission; filter every booking query by the authenticated user ID.
- Use PDO prepared statements with bound parameters for all user-controlled values.
- Protect every state-changing request with CSRF validation, and cancellation must use POST rather than GET.
- Validate the opaque booking reference on the server and re-check ownership, booking status, and departure time within the cancellation transaction.
- Escape all dynamic dashboard output with `app_escape()` / `htmlspecialchars()` using the project's existing conventions.
- Do not expose SQL exceptions, credentials, or filesystem paths. Log server-side failures and return safe user-facing messages.
- Preserve secure session configuration and avoid adding new authentication/session mechanisms.

## Acceptance criteria

- A signed-in passenger can open the My Bookings dashboard from the navbar and sees only their own upcoming `pending` or `confirmed` reservations.
- Guests are sent through the existing sign-in flow; administrators cannot access passenger booking data or cancellation controls.
- Each active booking card contains accurate persisted itinerary and passenger/seat information and links to the existing print and PDF ticket routes.
- The empty state is helpful, and all dashboard/card actions remain usable on mobile and with keyboard navigation.
- A passenger can cancel an eligible booking only through a valid owner-scoped CSRF-protected POST.
- Cancellation is atomic, changes the status to `cancelled`, retains booking and manifest records, removes the canceled booking’s seat assignments, and releases seats for subsequent bookings.
- A cancellation cannot alter another account’s reservation, cancel a past-departure booking, repeat a cancellation, or free seats after a failed transaction.
- Existing ticket endpoints continue to enforce ownership and show accurate reservation status; no payment or boarding-pass claim is introduced.
- No schema migration or new external dependency is added unless separately approved.
- `ui-registry.md` documents the resulting dashboard styling without duplicating existing baseline patterns.

## Checks to run

- Run `php -l` on all changed PHP files and the existing test suite if present; add/run focused checks for passenger filtering, empty state, CSRF rejection, cancellation ownership/status/departure boundaries, transaction rollback, and seat reuse.
- Verify PDO connectivity without printing credentials. Test using isolated fixtures or a dedicated test database; do not delete, reset, or reseed the developer’s configured database.
- Confirm navbar routes by role: guest, passenger, and administrator.
- Confirm print and PDF ticket links still return the existing owner-authorized outputs and that canceled bookings display their canceled status if opened directly.
- Check Apache/PHP logs with `error_reporting(E_ALL)` for warnings, deprecated behavior, and undefined variables.
- Review the diff for prepared statements, output escaping, owner authorization, CSRF enforcement, transaction behavior, seat release, responsive overflow, and unrelated changes.

## Manual test steps

1. Start Apache and MariaDB in XAMPP and sign in as a passenger with at least one future active booking. Confirm “My bookings” is visible in the navbar.
2. Open the dashboard and verify the booking reference, status, route, schedule, traveler/seat information, and saved fare match the reservation.
3. Use “View / print ticket”; verify it opens the existing ticket page with correct itinerary and passenger details. Test print preview and download the PDF.
4. Open another passenger’s account and verify that passenger’s dashboard does not show the first passenger’s reservation.
5. Submit a cancellation using the dashboard form, confirm the prompt, then verify the success message, that the canceled booking no longer appears as active, and that its booking and manifest records remain.
6. Verify that released seats are available again to a subsequent reservation, while occupancy remains correct for other active bookings.
7. Attempt cancellation with a missing/invalid CSRF token, a modified/unknown reference, another passenger’s reference, a repeated cancellation, and a booking with past departure. Confirm each is rejected safely and no unrelated booking or seat assignment is changed.
8. Test a passenger with no upcoming bookings and verify the empty state and return-to-search action.
9. Sign out and verify the passenger link is absent for guests; sign in as an administrator and verify passenger-only dashboard access is denied and admin navigation remains unchanged.
10. Check the dashboard and navbar at desktop and narrow mobile widths, and confirm keyboard focus and status labels remain visible.
