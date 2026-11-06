# Implementation Prompt: Nigerian Flight Demo Seeds and Dashboard Pagination

## Goal

Provide safe, reusable Nigerian airport and sample-flight seed data, and add pagination to the administrator flight list. Pagination must show 10 flights per page and support browsing the complete result set without changing flight CRUD behavior.

## Skills read

- `php-pro` — vanilla PHP/PDO conventions, input handling, and safe database operations.
- `architect` — keep request handling, data access, and view rendering separated.
- `bootstrap-components` — accessible Bootstrap 5 pagination controls and responsive layout.
- `mysql-schema` — preserve airport/flight relationships and use MariaDB-compatible SQL.
- `imprint` — match the existing admin dashboard UI patterns.
- `AGENTS.md` — project security, schema compatibility, and approval workflow.

## Existing code inspected

- `AGENTS.md` — application structure, MariaDB 10.11.16 compatibility requirements, security rules, and implementation workflow.
- `database/schema.sql` and `database/migrations/06-flight-ticket-price.sql` — airport codes are unique; flights reference origin/destination airports, require positive prices on insert/update, and have a unique `(flight_number, departure_at)` key.
- `models/Flight.php` — currently loads all airports and flights and implements CRUD with PDO.
- `admin-flights.php` — admin authorization, POST/CSRF handling, validation, feedback, and dashboard data preparation.
- `views/admin/flights.php` — current flight table and Bootstrap admin dashboard.
- `includes/header.php`, `includes/navbar.php`, and `assets/css/auth.css` — shared page layout, admin navigation, and design tokens.
- `docs/database-erd.md`, `README.md`, and `ui-registry.md` — current setup guidance, flight rules, and UI conventions.

## Decisions or assumptions

- Deliver seed data as a manually run, reusable SQL file at `database/seeds/nigeria-flights.sql`; do not connect to or insert into the user's database automatically.
- Include several Nigerian airports and at least 12 clearly labeled demo flight occurrences across domestic routes, enough to verify a second page at 10 rows per page.
- Use Nigerian Naira amounts as sample fares, stored in the existing decimal fare column. Explicitly label the data as fictional demonstration schedules and fares, not real airline inventory.
- Make the seed file safe to run more than once and avoid overwriting pre-existing airport details or flight records. It must not delete data.
- Paginate only the admin flight list; keep airport options available for the create/edit form.
- Use a fixed page size of 10 and a validated positive `page` query parameter. Keep existing edit actions and safe form behavior consistent with the page.
- The user's database is currently missing `flights.ticket_price`; the already-created migration must be applied before the dashboard or price-bearing seed SQL can run. Do not execute it as part of this task.

## Files likely to change

- `database/seeds/nigeria-flights.sql` — Nigerian airport and sample flight records, repeat-safe and non-destructive.
- `models/Flight.php` — count flights and fetch a bounded page using bound integer `LIMIT`/`OFFSET`.
- `admin-flights.php` — validate page selection and provide paginated data/metadata to the view.
- `views/admin/flights.php` — show current range/total and accessible Bootstrap Previous, numbered, and Next controls.
- `README.md` — explain the optional demo seed, prerequisites, and pagination.
- `ui-registry.md` — capture pagination styling if it adds a distinct reusable UI pattern.

## Implementation requirements

- Seed recognizable Nigerian airports by IATA code, including at least Lagos (LOS), Abuja (ABV), Port Harcourt (PHC), Kano (KAN), and Enugu (ENU). Include other Nigerian airports only where the code/name mapping is sufficiently reliable.
- Create at least 12 fictional domestic flight occurrences with different departure datetimes and positive, two-decimal NGN fares. Use future-relative dates or clearly documented demo dates, never claim to provide real airline schedules.
- Use MariaDB-compatible, non-destructive SQL. Do not use `TRUNCATE`, `DELETE`, or destructive table resets. Do not overwrite airport details or existing flight data on repeated imports. Reference airports by their unique IATA codes rather than assuming auto-increment IDs.
- Ensure all seeded flights satisfy the existing origin/destination, time, capacity, fare, and uniqueness rules. State that the fare migration must have been applied first.
- Add `Flight::countFlights()` and a paged flight-list method (or equivalently clear methods), returning typed arrays consistent with the current model. Restrict results with prepared `LIMIT` and `OFFSET` integer parameters, not string interpolation.
- Calculate total pages and the displayed record range from the count. Validate `page` server-side; clamp requests beyond the last page to a valid page, and handle an empty result set without invalid offsets or misleading ranges.
- Keep the page parameter in edit links, and provide keyboard-accessible Bootstrap pagination markup with `aria-label`, `aria-current="page"`, disabled Previous/Next states, and descriptive accessible labels. Avoid rendering an unbounded page-number list; show a useful nearby range for large result sets.
- Preserve the current authorization, CSRF, create/edit/delete actions, error feedback, and airport options.
- Display prices clearly as NGN/₦ on the dashboard so sample Naira values have an explicit unit. Use HTML-safe formatting and UTF-8 output.
- Update README with the exact optional phpMyAdmin import path, the requirement to apply the fare migration first, and a reminder that the seed records are fictional demo data.
- Do not add a framework, JavaScript dependency, live airline integration, search filters, airport-management UI, or automatic database seeding.

## Security requirements

- Continue to enforce the admin role in the request handler; pagination parameters never grant access.
- Use prepared statements for queries and bind `LIMIT`/`OFFSET` as integers.
- Validate the query-string page value; do not use it to construct SQL.
- Render all flight, airport, and pagination values with `app_escape()` or equivalent UTF-8 `htmlspecialchars()` flags.
- Seed SQL must contain no credentials, avoid destructive operations, and avoid updates to existing airport/flight rows.
- Never run seed SQL or migrations automatically from a page request.

## Acceptance criteria

- `database/seeds/nigeria-flights.sql` supplies recognizable Nigerian airports and at least 12 fictional, valid domestic flight examples, and can be safely rerun without duplicating or overwriting existing rows.
- The admin flight list shows at most 10 records per page, along with a correct range and total count.
- Previous, page-number, and Next controls work for first, middle, and last pages; direct page values are validated and out-of-range values are handled safely.
- Editing a flight from any page preserves the current page when returning to the list.
- The create/edit form still includes the full airport list and CRUD operations remain functional.
- Dashboard fares clearly indicate NGN, all dynamic output remains escaped, and guests/passengers remain excluded.
- Documentation explains how to optionally import seeds after the fare migration and warns that sample schedules/prices are fictional.

## Checks to run

- Run PHP syntax checks on changed PHP files using `C:\xampp\php\php.exe`.
- Run `git diff --check`.
- Verify page parsing and page-window calculations for empty results, exactly 10 records, 11+ records, non-numeric/negative input, and a request beyond the final page.
- Review pagination SQL to verify parameterized integer bounds and prepared statements.
- If an isolated/disposable MariaDB environment is available, import the seed twice and verify no duplicate/overwritten rows and valid route/fare constraints. Do not execute seed SQL against the user's active project database.
- Review the migration prerequisite and the README instructions; do not run the migration or seeds automatically.
- If Apache is running, verify the existing admin route still redirects guests and review PHP/Apache logs for warnings.

## Exact manual test steps expected after implementation

1. Start Apache and MariaDB in XAMPP and open phpMyAdmin.
2. Select the project database and confirm `flights.ticket_price` exists. If it does not, apply `database/migrations/06-flight-ticket-price.sql` before importing seeds.
3. Optionally select the project database, choose **Import**, and import `database/seeds/nigeria-flights.sql`. The file contains fictional demonstration schedules and fares only.
4. Confirm the Nigerian airport records exist and the seed flight routes use airport codes rather than assumed numeric IDs. Re-import the seed and confirm it does not create duplicates or change existing airport details.
5. Sign in with an admin account and open `http://localhost/airline-reservation-system/admin-flights.php`.
6. With more than 10 flights, confirm page 1 shows 10 rows and accurate range/total text; select page 2 and verify the next rows.
7. Test Previous/Next and first/last page disabled states. Try `?page=abc`, `?page=-1`, and a page number above the total; confirm safe handling.
8. Edit a flight from page 2, save it, and confirm the list returns to page 2. Verify fares are labeled NGN.
9. Confirm the create form's origin/destination selectors still contain all available airports and normal CRUD behavior remains unchanged.
10. Visit the dashboard as a guest and as a passenger; confirm both remain redirected to login.
