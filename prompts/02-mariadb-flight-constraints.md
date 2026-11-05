# Implementation Prompt: MariaDB-Compatible Flight Constraints

## Goal

Make the database schema import successfully on the project's XAMPP MariaDB 10.11.16 server after error `#1901` rejected the `flights` table's `CHECK` constraints. Preserve the same three flight invariants using MariaDB-compatible triggers, and allow the corrected schema to complete installation without deleting the already-created `users` and `airports` tables.

## Skills read

- `mysql-schema` — relational schema design, constraints, indexes, and ERD documentation.
- `php-pro` — project PHP conventions and database configuration context.
- The `mysql-schema` skill references `.agents/skills/mysql-shared/SKILL.md`, but that file is absent from this workspace; no `mysql-cli` actions are needed for this DDL change.

## Existing code inspected

- `AGENTS.md` — project workflow, database/security requirements, and approval gate.
- `database/schema.sql` — current five-table DDL and the three rejected `flights` checks.
- `docs/database-erd.md` — current ERD and check-constraint documentation.
- `config/db.php` — PDO connection uses process environment variables; no change is expected.
- Existing local database state reported by the user: `users` and `airports` exist; `flights`, `bookings`, and `passengers_manifest` do not.
- XAMPP phpMyAdmin reports MariaDB 10.11.16; server error is `#1901 - Function or expression 'origin_airport_id' cannot be used in the CHECK clause of chk_flights_distinct_airports`.

## Decisions and assumptions

- Preserve the integrity rules that origin and destination differ, arrival is after departure, and seat capacity is positive; do not silently remove validation.
- Enforce those rules with `BEFORE INSERT` and `BEFORE UPDATE` triggers on `flights`, raising explicit errors with `SIGNAL SQLSTATE '45000'`.
- Make the schema safe to re-import into the user's current database without dropping tables or data. Existing `users` and `airports` tables are assumed to have been created from the current `database/schema.sql`.
- Use `CREATE TABLE IF NOT EXISTS` for the schema's tables so a repeat import can retain the two existing tables and create the missing dependent tables. Document that this clause does not repair a pre-existing table with a mismatched definition.
- Use an import-compatible, repeatable trigger setup that replaces only the named project triggers and never drops tables or the database.
- Do not add a `.env` loader, dependencies, application features, or unrelated schema changes.

## Files likely to change

- `database/schema.sql` — MariaDB-compatible, repeatable schema installation and trigger definitions.
- `docs/database-erd.md` — document the trigger-enforced flight invariants and updated installation/version notes.

## Implementation requirements

- Keep all five tables, existing columns, primary/unique keys, indexes, and foreign-key delete/update policies unchanged.
- Remove the three unsupported `CHECK` declarations from the `flights` table definition.
- Add triggers for both insert and update that reject:
  - identical `origin_airport_id` and `destination_airport_id`;
  - `arrival_at` less than or equal to `departure_at`;
  - `seat_capacity` equal to zero.
- Raise actionable database errors using `SIGNAL SQLSTATE '45000'`; do not silently coerce invalid values.
- Ensure all DDL is valid for MariaDB 10.11.16 and importable through phpMyAdmin. Preserve dependency order: `users` and `airports`, then `flights`, `bookings`, and `passengers_manifest`.
- Keep the installation safe for the current partial state. Do not `DROP DATABASE`, drop existing tables, or erase data.
- Update the ERD documentation to explain that flight invariants are enforced by triggers rather than `CHECK` constraints.
- Leave `config/db.php` and credentials unchanged.

## Security and integrity requirements

- Keep all foreign keys and restrictive delete policies that protect reservation history.
- Do not interpolate request data into SQL or weaken existing natural-key, enum, or foreign-key constraints.
- Never expose database credentials or add credentials to files.
- Trigger validation must run on both inserts and updates so updates cannot bypass the invariants.

## Acceptance criteria

- Importing the corrected schema over the current partial database preserves `users` and `airports`, creates `flights`, `bookings`, and `passengers_manifest`, and creates the two validation triggers.
- Re-importing the corrected schema does not drop existing data or fail merely because the two existing tables or project triggers already exist.
- Valid flight rows satisfy all three invariants; invalid insert and update attempts are rejected explicitly.
- The ERD documentation accurately describes trigger enforcement and all foreign-key relationships/policies.
- No live database import or trigger test is claimed unless actually executed against the user's MariaDB instance.

## Checks to run

- Review generated SQL for MariaDB 10.11 syntax, dependency order, and repeat-import safety.
- Run any available project SQL/static checks; do not install new tooling only for this task.
- If an accessible MariaDB instance is available, import into a disposable test database and verify the tables, indexes, foreign keys, triggers, and rejection behavior. Do not run destructive commands against the user's existing database.
- Report clearly if local server validation is unavailable; the user can then perform the exact manual test below.

## Exact manual test steps

1. Keep the existing `airline_reservation` database; do not delete it.
2. In phpMyAdmin, select `airline_reservation` and choose **Import**.
3. Import the corrected `database/schema.sql`.
4. Confirm that all five tables exist and that `users` and `airports` remain intact.
5. In phpMyAdmin, inspect the `flights` table's foreign keys and indexes, and inspect its triggers under the database/table trigger view.
6. With valid airport IDs, attempt a flight insert with identical origin/destination; confirm MariaDB rejects it with the explicit trigger message.
7. Attempt inserts with arrival at/before departure and with zero seat capacity; confirm both are rejected.
8. Insert one valid flight and confirm it succeeds. Remove only any test rows added for this check.
9. Repeat the import once and confirm it completes without dropping existing tables or data.
