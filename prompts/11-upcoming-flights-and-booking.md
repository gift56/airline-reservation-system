# Upcoming Flights and Passenger Booking

## Goal

Add near-term, rerunnable fictional Nigerian flight seed data so the public landing page has bookable options. Show upcoming available flights before search, and let an authenticated passenger reserve seats for 1–6 travelers on a selected flight. Booking creates a pending reservation and manifest; no payment is collected or implied.

## Skills read

- `.agents/skills/mysql-schema` — schema and seed conventions. Its referenced `../mysql-shared/SKILL.md` is absent from the workspace.
- `.agents/skills/php-pro` — PDO, validation, session, and secure form handling.
- `.agents/skills/architect` — current architecture and implementation alignment.
- `.agents/skills/bootstrap-components` — responsive forms, cards, and accessible feedback.

## Existing code inspected

- `AGENTS.md` — implementation approval workflow, architecture, security, database compatibility, and manual verification expectations.
- `database/seeds/nigeria-flights.sql` — current fictional six-airport seed and 2030-only flight schedules.
- `database/schema.sql` and `docs/database-erd.md` — MariaDB 10.11-compatible tables, pending/confirmed/cancelled booking statuses, booking references, and manifest structure.
- `models/Flight.php` — PDO flight search and available-seat calculation.
- `config/db.php` and `config/session.php` — PDO setup, session identity, CSRF, flash, and HTML escaping.
- `index.php`, `includes/navbar.php`, `login.php`, and `register.php` — current landing page, guest/account navigation, and authentication redirects.
- `assets/css/flight-search.css` — current public search and flight card design.
- Local environment observed: XAMPP PHP 8.4.27 and MariaDB 10.11.16. The configured database currently has no airport rows.

## Decisions and assumptions

- Add fictional flight schedules in a near-term rolling window using MariaDB-compatible date expressions. The seed must be safe to re-run without duplicates and must not delete or overwrite user-managed records.
- Keep existing 2030 sample rows; they are not a substitute for flights in the next few weeks.
- Show a modest list of upcoming, scheduled, positively priced flights with remaining seats on the initial public landing page; retain exact route/date search and filters.
- A booking means a reservation, not ticket payment. Do not integrate a payment gateway, claim a ticket is paid/confirmed, or collect payment details.
- One booking may include 1–6 travelers. Store one `passengers_manifest` row per traveler.
- Booking requires a signed-in passenger. A guest selecting a flight should be sent through sign-in/registration and returned to that flight without accepting arbitrary redirect URLs.
- A booking initially uses the existing `pending` status. Pending and confirmed reservations both consume capacity, as in the existing search logic. No expiry or cancellation workflow is introduced here.
- Reserve capacity within a database transaction while locking the selected flight row; count current pending/confirmed manifests before inserting. A failed capacity check must leave no booking or partial manifest rows.
- Use the existing Bootstrap/Font Awesome flight-card styling and keep all prices in Nigerian naira (`₦`).

## Files likely to change

- `prompts/11-upcoming-flights-and-booking.md` — approved implementation specification.
- `database/seeds/nigeria-flights.sql` — idempotent, near-term sample schedules.
- `models/Flight.php` — query for a bounded set of upcoming available flights if the existing search method cannot be reused.
- `models/Booking.php` — transactionally create reservation and traveler manifests with capacity validation.
- `index.php` — initial upcoming flights and “Book” actions.
- `book-flight.php` — authenticated booking form, validation, reservation result/feedback, and safe guest return path.
- `login.php` and `register.php` — preserve and validate an optional internal booking return target through the current auth flow.
- `assets/css/flight-search.css` — booking-specific responsive presentation if needed.
- `README.md` — seed import/re-run and booking/payment scope instructions.

## Implementation requirements

### Seed data and available flights

- Keep all seeded airports and flight data clearly identified as fictional demonstration data with NGN fares.
- Add enough schedule variety across existing Nigerian airports and routes to populate upcoming-flight cards and date-based search for the near term (for example, multiple flights per day across roughly the next 2–3 weeks).
- Generate near-term departure/arrival dates using MariaDB 10.11-compatible SQL, not fixed dates already in the past or years in the future.
- Preserve the seed's idempotence for repeated runs on the same day using the existing flight identity (flight number and departure timestamp). Do not use destructive cleanup, truncate, drop, or update existing flight records.
- Confirm each generated departure is after the seed's current date and arrival is later than departure; provide valid distinct airport references, positive fare, and positive capacity.
- Add or reuse a `Flight` model method that returns a bounded number of future scheduled, priced flights with remaining seats, ordered by departure. Use the same occupancy definition as search: pending/confirmed bookings consume at least one seat each, or the number of manifest travelers when present. Exclude fully occupied flights.
- Display these flights on the initial landing page without requiring search input. Retain the current search results behavior and include clear empty/loading-unavailable messaging.

### Booking flow

- Render a prominent booking link/action on upcoming/search result flight cards that includes only a validated flight identifier and leads to `book-flight.php`.
- Require a passenger session for reservation submission. Admin accounts must not create passenger reservations through this route.
- For guests, preserve the selected flight while directing them to sign in or register. Validate/allowlist the internal return destination and never redirect to a user-supplied external URL.
- Provide a responsive booking form for 1–6 travelers. Collect only first and last names required by the existing manifest table; validate every submitted name server-side.
- Show the selected flight, route, schedule, per-person Naira fare, traveler count, and total fare for information. Clearly state that this step reserves seats and does not charge payment or confirm payment.
- Add an opaque 24-character booking reference using a cryptographically secure generator compatible with `booking_reference CHAR(24)`.
- Create one pending booking and its manifest rows atomically. Use PDO prepared statements, begin/commit/rollback, and lock the flight row before recounting capacity. Reject past, non-scheduled, unpriced, or insufficient-capacity flights.
- Ensure any exception after starting the transaction rolls it back and is logged server-side; show a safe user message without database details.
- On success, show the booking reference, reserved flight details, and pending status; do not imply an issued ticket, a charge, or confirmed payment.
- Do not add a new schema table/column unless inspection demonstrates the existing tables cannot support this flow. Update schema/ERD documentation if a schema change is necessary and validate it against MariaDB 10.11.16 first.

### Visual and accessibility requirements

- Reuse the landing page's dark navbar, violet action color, rounded white cards, responsive Bootstrap 5 grid, and Font Awesome patterns.
- Keep flight cards visually consistent before search and in search results. The booking action must be clear and keyboard accessible.
- Present booking form labels, validation errors, selected itinerary, price, and pending state in semantic accessible markup; do not rely on icon/color alone.
- Ensure booking forms and cards stack cleanly on mobile without horizontal overflow.

## Security requirements

- Use PDO prepared statements for every query or write.
- Validate flight IDs, traveler counts, traveler names, session role, and CSRF token server-side.
- Escape all dynamic HTML output using `app_escape()`.
- Protect booking POST requests with `app_csrf_token()` / `app_is_valid_csrf_token()`.
- Keep booking/payment claims accurate: no online payment is performed; do not store card/payment credentials.
- Use a cryptographically secure booking reference; do not expose sequential database IDs as public booking references.
- Preserve secure session behavior and login role controls. Validate any return path as an internal booking route for the selected flight only.
- Do not expose PDO exception details or credentials to visitors.

## Acceptance criteria

- Re-running the seed on the same day does not duplicate its near-term airport/flight rows or alter existing data.
- The seed includes multiple upcoming fictional schedules across Nigerian routes with valid dates, fares, and capacity.
- The landing page displays future available flights before a visitor searches; fully occupied, cancelled, past, and unpriced flights are absent.
- Search results still work and expose a functional booking action for each eligible flight.
- A passenger can reserve 1–6 travelers with valid first/last names; the database contains one pending booking and exactly one manifest row per traveler.
- Booking creation is atomic and capacity is rechecked under a row lock; concurrent or oversized bookings cannot oversell seats.
- Guests can continue from their selected flight through sign-in/registration and return to the intended booking safely; administrator accounts cannot book as passengers.
- Confirmation presents the booking reference and pending status and explicitly does not claim that payment occurred.
- Invalid flight IDs, CSRF tokens, traveler counts/names, insufficient availability, and database failures are handled safely.
- No payment fields/provider, destructive seed cleanup, unrelated account/history functionality, or unrequested schema change is introduced.

## Checks to run

- Run PHP syntax checks on changed PHP files and the existing project test suite if present.
- Verify PDO connectivity and identify the running database engine/version (currently observed as MariaDB 10.11.16).
- Test seed idempotence and result dates against a disposable test database or transaction; do not delete/reseed the developer's configured database.
- Verify booking rollback and capacity enforcement against temporary test fixtures or a dedicated non-production test database.
- Check the Apache/PHP logs for warnings, deprecated behavior, and undefined variables.
- Review the changed diff to confirm there are no unrelated edits and no payment claims or arbitrary redirect handling.

## Manual test steps

1. In phpMyAdmin, select the configured airline database and import `database/seeds/nigeria-flights.sql` without dropping tables or deleting existing data.
2. Re-import the seed on the same day and verify flight counts do not increase for seeded schedule rows.
3. Visit `http://localhost/airline-reservation-system/` and confirm upcoming flight cards show route, date/time, remaining seats, fare in `₦`, and a booking action.
4. Search a seeded route/date and confirm matching flights remain bookable; search a date/route without flights and confirm the empty state.
5. As a guest, select a flight, sign in or register, and verify the site returns to the selected flight's booking form.
6. Submit a booking for one traveler, then for multiple travelers up to six; verify a unique 24-character reference, pending status, and corresponding manifest rows.
7. Attempt a booking with more travelers than remaining seats and verify no booking or partial manifest rows are created.
8. Test invalid flight identifiers, a tampered return path, missing/invalid CSRF, malformed names, and a direct POST while signed out; confirm safe errors and no unauthorized booking.
9. Sign in as an administrator and confirm booking submission is rejected.
10. Confirm the page and booking confirmation state that payment is not processed, and verify no payment details are requested.
