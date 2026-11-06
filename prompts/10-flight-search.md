# Public Flight Search and Landing Page

## Goal

Build the public landing and flight search page at `index.php`, following the supplied `assets/referral.jpg` reference. Guests should be able to search future flights by origin, destination, and departure date, then refine results using filters supported by the current database. Give the shared navigation and existing passenger login/registration screens a premium, cohesive visual treatment without changing their authentication behavior.

## Skills read

- `.agents/skills/php-pro`
- `.agents/skills/bootstrap-components`
- `.agents/skills/architect`

## Existing code inspected

- `AGENTS.md` — project workflow, architecture, security, and verification requirements.
- `config/db.php` and `config/session.php` — PDO setup, session, CSRF, identity, and escaping helpers.
- `models/Flight.php` — airport listing and existing flight queries.
- `database/schema.sql` — airports, flights, bookings, passenger manifests, and flight constraints.
- `includes/header.php`, `includes/navbar.php`, and `includes/footer.php` — shared page shell and navigation.
- `login.php` and `register.php` — existing authentication flow and form handling.
- `assets/css/auth.css` and `ui-registry.md` — current styling patterns.
- `assets/referral.jpg` — user-provided visual reference.

## Decisions and assumptions

- “Available” means a future flight with `status = 'scheduled'`, a positive ticket price, and at least one unoccupied seat. Determine occupied seats from passenger-manifest rows for pending or confirmed bookings; do not count cancelled bookings.
- Search results must match the selected origin, destination, and calendar departure date. Do not silently show unrelated flights when search criteria are submitted.
- Functional sidebar filters are limited to price and departure/arrival time, which are represented in the existing schema. Do not show nonfunctional aircraft category, discount, or operator-rating controls.
- Follow the reference’s dark compact header, pale neutral canvas, white rounded search/results panels, aircraft imagery, and vivid violet search accent while retaining the existing airline identity and keeping authentication/session behavior intact.
- Use Font Awesome icons. Use locally stored, license-compatible aircraft imagery if available or can be sourced and verified; do not hotlink images or use the screenshot itself as an aircraft image. If no suitable image can be obtained, use an accessible visual fallback rather than a broken image.
- Do not add a booking workflow or link to a nonexistent booking endpoint. This task is search, filtering, and presentation only.

## Files likely to change

- `prompts/10-flight-search.md` — this approved implementation specification.
- `index.php` — guest-accessible landing page, search handling, validation, and results.
- `models/Flight.php` — prepared search query and typed result mapping, reusing existing model conventions.
- `includes/header.php` — shared styles/icon assets as needed.
- `includes/navbar.php` — responsive premium navigation and existing guest/authenticated links.
- `login.php`, `register.php`, `assets/css/auth.css` — presentation-only refinements to preserve current authentication flow.
- A focused stylesheet under `assets/css/` and local aircraft image assets under `assets/images/`, only if needed.
- `ui-registry.md` — record the completed visual patterns after building the UI.

## Implementation requirements

### Search and results

- Use the current PDO connection from `config/db.php` and `App\Models\Flight`; add a dedicated search method rather than embedding SQL in the page template.
- Populate origin and destination controls from the existing airport list. Keep the chosen values, date, and supported filter values after submission.
- Validate airport IDs against the airport list, require different origin and destination airports, and validate the date and optional filter ranges before querying.
- Use PDO prepared statements with bound parameters for all submitted criteria. Ensure date filtering covers the selected calendar day without string-concatenated SQL.
- Return only future scheduled flights with a positive fare and available seats. Count occupied seats from passenger manifests associated with pending/confirmed bookings, and exclude flights where occupied seats meet or exceed `seat_capacity`.
- Support price filtering and departure/arrival time filtering; provide a useful deterministic sort (lowest fare by default, with departure time as a tie-breaker).
- Render flight cards with the flight number, origin/destination city and airport code, departure/arrival times, duration, remaining seats, and fare. Format fare in Nigerian Naira (`₦`), never dollars.
- Include empty, validation-error, and database-unavailable states with clear accessible messages. Log database failures server-side and do not expose exception details.
- Do not create a booking action that points to an absent endpoint.

### Visual design and responsive behavior

- Treat `assets/referral.jpg` as the layout reference: compact dark top navigation; prominent horizontal search panel; suggested destination chips only when they can be given meaningful behavior; results heading/count and sort control; flight cards in the primary column; and a filter panel beside results on desktop.
- Adapt the reference’s card composition to actual flight data. Do not fabricate discounts, aircraft categories, operator scores, fare counts, or flight inventory.
- Use Bootstrap 5 responsive grid utilities: stack search fields and filter panel on narrow screens, and use a results-plus-filter layout at desktop widths.
- Match the reference with generous whitespace, subtle borders/shadows, rounded white surfaces, strong route typography, clear fare emphasis, and a violet primary search action. Keep labels and states readable with sufficient contrast.
- Update shared navigation so the brand links to `index.php`, the mobile toggle remains functional, and existing sign-in/account/register/admin/logout behavior remains intact.
- Polish the existing login/register appearance to match the landing-page design while preserving their current POST targets, validation, CSRF protections, role handling, and redirects.
- Use Font Awesome icons with accessible names or `aria-hidden="true"` when the adjacent text already conveys meaning. Provide meaningful alt text for aircraft imagery.
- Avoid unnecessary third-party requests; use existing Bootstrap integration and a reliable Font Awesome stylesheet source. Do not add a JavaScript framework.

## Security requirements

- Use PDO prepared statements and bound parameters for all user-controlled search/filter values.
- Escape all dynamic HTML output with the project’s `app_escape()` helper.
- Keep public search guest-accessible; do not weaken session authorization elsewhere.
- Preserve existing authentication CSRF handling, password handling, session protections, and role-based links.
- Validate submitted data server-side; HTML input constraints are supplemental only.
- Do not expose database exceptions or configuration values in the response.

## Acceptance criteria

- `index.php` loads for guests and presents a responsive landing/search interface inspired by the supplied image.
- A valid origin, destination, and date query returns only matching, future, scheduled, priced flights with remaining seats.
- Search inputs and supported filters remain selected after submission; invalid input is rejected with a clear message.
- Price and departure/arrival-time filters affect the result set; unsupported image filters are omitted.
- Flight details display correctly and all fares use `₦`.
- No-match and database-error states render intentionally and accessibly.
- Navbar links, mobile collapse, and existing login/register behavior continue to work.
- Login and registration look consistent with the new design without changes to the authentication flow.
- Aircraft imagery is local and appropriately licensed, or a deliberate accessible fallback is used.
- `ui-registry.md` documents the final shared design patterns.

## Checks to run

- Run PHP syntax checks on changed PHP files.
- Run existing project tests or targeted checks if present.
- Verify PDO connectivity through the configured project connection when the local database is available.
- Review PHP/Apache logs for warnings, deprecated behavior, and undefined variables.
- Inspect the changed diff to confirm auth logic and unrelated admin behavior were not altered.

## Manual test steps

1. Start Apache and MySQL/MariaDB in XAMPP and open `http://localhost/airline-reservation-system/`.
2. Verify the landing page and navbar render at desktop and mobile widths; use the mobile menu with keyboard and pointer.
3. Select two different airports and a date with scheduled flights; submit and confirm every result matches the route and selected calendar day.
4. Confirm flights that are past, cancelled, unpriced, or fully occupied are not displayed.
5. Apply price, departure-time, and arrival-time filters; verify results change and selected values persist.
6. Submit identical origin/destination values, malformed airport IDs, an invalid date, and invalid filter bounds; confirm validation feedback and no query errors.
7. Search a route/date with no matching flights and confirm the empty state.
8. Confirm all displayed fares use `₦` and no unsupported filters or nonfunctional booking links appear.
9. Open sign-in and passenger registration from the navbar; verify the new presentation while successful and failed submissions retain existing behavior.
10. Sign in with passenger and administrator test accounts; confirm role-specific navbar links and sign-out continue to work.
