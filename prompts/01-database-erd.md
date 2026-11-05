# Implementation Prompt: Database Schema & ERD Model Setup

## Goal

Implement the first database foundation for the Airline Reservation System: a normalized MySQL schema for users, airports, scheduled flights, bookings, and individual passengers on each booking; document the ERD relationships; and add a reusable PDO connection in `config/db.php`.

## Skills read

- `mysql-schema` — relational schema design, foreign keys, indexing, ERD mapping
- `php-pro` — secure PHP database connection patterns
- `architect` — database/application separation and implementation alignment

## Existing code inspected

- Read the root `AGENTS.md` for product scope, architecture, security, and workflow requirements.
- Inspected the workspace file listing. No existing PHP application files, SQL schema, or database configuration were present.
- The referenced `.agents/skills/mysql-shared/SKILL.md` was not present in the workspace; no `mysql-cli` operation is needed for this implementation.

## Decisions and assumptions

- A `users` row represents an account holder and has a `role` distinguishing `passenger` from `admin`.
- One `booking` belongs to one account holder and one scheduled flight. It may contain multiple travelers; each traveler is a separate `passengers_manifest` row.
- A `flights` row represents one scheduled flight occurrence, not an abstract route. It references distinct origin and destination airports and has departure/arrival timestamps and a seat capacity.
- Keep manifest traveler data separate from user account data so travelers do not need their own accounts. Store only the traveler details needed for a basic manifest; do not add payment, passport, or unrelated operational systems.
- Derive the number of travelers in a booking from the manifest rather than storing a duplicate passenger count.
- Use InnoDB, `utf8mb4`, explicit foreign-key actions, and indexes for foreign keys and common lookup paths. Use MySQL-compatible data types and constraints available in the XAMPP deployment target.
- Database credentials are supplied through environment variables (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`); do not commit secrets or hardcode production credentials.

## Files likely to change

- `database/schema.sql` — schema creation script
- `config/db.php` — PDO connection setup
- `docs/database-erd.md` — concise textual ERD and relationship documentation (or a suitable existing database document if one is added before implementation)

## Implementation requirements

### Schema

Create the five requested tables:

1. **`users`**
   - Primary key; account name, unique email, password hash, role (`passenger` or `admin`), and created/updated timestamps.
   - Store only password hashes; do not include a plain-text password column.

2. **`airports`**
   - Primary key; unique IATA code, airport name, city, and country.
   - Keep airport attributes in this table rather than duplicating names/codes in flight rows.

3. **`flights`**
   - Primary key; flight number; origin and destination airport foreign keys; scheduled departure and arrival timestamps; seat capacity; and a constrained operational status.
   - Prevent an airport from being both origin and destination where supported by the selected MySQL version.
   - Index airport references, departure time, and fields used for flight search.

4. **`bookings`**
   - Primary key; unique, non-guessable-format booking reference; account-holder user foreign key; scheduled-flight foreign key; constrained booking status; and created/updated timestamps.
   - Do not make `(user_id, flight_id)` unique unless an existing requirement is discovered; repeated reservations for the same flight must not be silently prohibited.
   - Index user, flight, status, and booking reference lookup paths.

5. **`passengers_manifest`**
   - Primary key; booking foreign key; traveler name fields and only the minimum additional traveler detail needed by the basic booking flow (for example, date of birth if justified).
   - One row per traveler; index the booking foreign key.
   - Do not link each manifest traveler to `users`; the account holder is already represented by `bookings.user_id`.

Use appropriately sized unsigned integer keys, decimal currency types only if a fare is actually included, `NOT NULL` where the workflow requires a value, unique constraints for natural identifiers, and explicit `ON DELETE`/`ON UPDATE` behavior. Avoid redundant attributes and avoid relying on application-only defaults for schema integrity. Ensure airport codes and role/status values have appropriate length and validation constraints.

### ERD relationships

Document these cardinalities and foreign keys:

- `airports` 1 — 0..many `flights` as origin; `flights.origin_airport_id` references `airports.id`.
- `airports` 1 — 0..many `flights` as destination; `flights.destination_airport_id` references `airports.id`.
- `users` 1 — 0..many `bookings`; `bookings.user_id` references `users.id`.
- `flights` 1 — 0..many `bookings`; `bookings.flight_id` references `flights.id`.
- `bookings` 1 — 1..many `passengers_manifest` rows for a completed passenger reservation; `passengers_manifest.booking_id` references `bookings.id`. The database may permit zero rows during booking creation if the workflow needs a transaction to create booking and travelers together; document the selected behavior.

Include a compact text ERD (or Mermaid ER diagram if the repository has no established documentation format) and list each foreign key's delete/update policy.

### PDO configuration

Implement `config/db.php` as a small reusable connection entry point appropriate to the existing vanilla PHP project:

- Read `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` from the process environment; do not expose credentials in output or commit them.
- Use a MySQL DSN with `charset=utf8mb4`.
- Enable `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`, `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`, and disable emulated prepares.
- Return or otherwise expose a `PDO` instance using the simplest convention consistent with the project.
- Fail explicitly with a useful configuration/connection exception; do not swallow connection errors or return a success-shaped fallback.
- Declare strict types and type declarations consistent with the project PHP version. Do not introduce a framework or dependency solely for configuration.

## Security requirements

- Any future queries that consume user input must use PDO prepared statements with bound parameters.
- Password storage is represented only by a `password_hash` value; application code must use `password_hash()` and `password_verify()`.
- Do not place secrets in source control. Environment configuration must be kept out of public exposure and documented without real credentials.
- Use least-privilege foreign-key behavior; avoid cascading deletion of user or flight records when it could erase reservation history. Choose and document `RESTRICT`/`CASCADE`/`SET NULL` per relationship.
- Do not add SQL that interpolates request data. Escape dynamic output in any PHP output added by this implementation.

## Acceptance criteria

- `database/schema.sql` creates the five requested InnoDB tables in a valid dependency order and can be imported into a clean supported MySQL/MariaDB database.
- The schema enforces primary keys, unique identifiers, required fields, role/status domains, foreign keys, and relevant indexes without storing duplicated airport or account data.
- The documented ERD matches the actual schema, including the two flight-to-airport roles and the one-to-many booking manifest.
- `config/db.php` creates a working PDO connection when valid environment values are configured, and throws explicitly when configuration or connection is invalid.
- No production credentials are hardcoded, and no unrelated product features are introduced.

## Checks to run

- Run PHP syntax validation on `config/db.php` (`php -l config/db.php`).
- Import `database/schema.sql` into a clean local MySQL/MariaDB database using the available XAMPP/phpMyAdmin or MySQL client, and verify all five tables, indexes, constraints, and foreign keys.
- Verify PDO connection with valid environment variables, then verify invalid credentials/configuration produce an explicit error without revealing secrets.
- Check Apache/PHP logs after the connection check for warnings, deprecated behavior, or undefined variables.
- If no local database service is available, report that limitation and still run syntax validation; do not claim the schema import or live connection was tested.

## Exact manual test steps

1. Start MySQL in XAMPP and create an empty database named `airline_reservation` with `utf8mb4` character set/collation.
2. Import `database/schema.sql` through phpMyAdmin (or the configured MySQL client).
3. In phpMyAdmin, confirm `users`, `airports`, `flights`, `bookings`, and `passengers_manifest` exist with InnoDB and inspect their primary keys, unique keys, indexes, and foreign keys.
4. Set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` in the local PHP process/environment to match the local database, then load a small connection-check entry point or include `config/db.php` from a temporary local check.
5. Confirm the valid configuration returns a PDO instance and inspect PHP/Apache logs for warnings.
6. Temporarily use an invalid database name or password and repeat the check; confirm an explicit connection failure occurs and that the error output does not disclose credentials. Restore the valid environment values afterward.
