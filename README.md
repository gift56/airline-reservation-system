# Airline Reservation System

A PHP and MariaDB project scaffold for an airline reservation system. The repository provides a PDO connection entry point, a MySQL/MariaDB schema for accounts, airports, scheduled flights, bookings, and passenger manifests, an ERD, and passenger registration/login with shared Bootstrap account UI.

> **Current status:** The database schema, passenger registration/login, public flight search, passenger pending reservations, shared account UI, and administrator flight CRUD dashboard are implemented. Booking history and online payment are not implemented.

## Contents

- [Technology and verified local environment](#technology-and-verified-local-environment)
- [Start the local XAMPP services](#start-the-local-xampp-services)
- [Create and import the database](#create-and-import-the-database)
- [Database schema and relationships](#database-schema-and-relationships)
- [Configure PHP database credentials](#configure-php-database-credentials)
- [Test the PDO connection from PowerShell](#test-the-pdo-connection-from-powershell)
- [Verify the database](#verify-the-database)
- [Authentication and sessions](#authentication-and-sessions)
- [Public flight search and passenger reservations](#public-flight-search-and-passenger-reservations)
- [Admin flight dashboard](#admin-flight-dashboard)
- [Re-import behavior and safety](#re-import-behavior-and-safety)
- [Troubleshooting](#troubleshooting)
- [Current project limitations](#current-project-limitations)

## Technology and verified local environment

The project is intended for vanilla PHP, PDO, MySQL-compatible SQL, XAMPP Apache, and phpMyAdmin. The following versions were observed in the local setup used to import and validate the schema; they are **observations, not universal minimum requirements**:

| Component | Observed local version |
|---|---:|
| XAMPP Control Panel | 3.3.0 |
| Apache | 2.4.65 |
| PHP | 8.4.27 |
| MariaDB | 10.11.16 |
| phpMyAdmin | 5.2.3 |

Confirm the database engine and version on your own machine before applying SQL. MySQL Workbench is a client and does not identify which database server XAMPP/phpMyAdmin is currently using.

## Start the local XAMPP services

1. Open the XAMPP Control Panel. If Windows prevents XAMPP from saving its settings, close it and choose **Run as administrator** when reopening it.
2. Click **Start** next to **Apache** and **MySQL** (the XAMPP MySQL module runs MariaDB in this observed installation).
3. Open [http://localhost/phpmyadmin](http://localhost/phpmyadmin) and confirm phpMyAdmin displays the connected database server.
4. The project is stored under `C:\xampp\htdocs\airline-reservation-system`.

Apache and MySQL use different ports. Apache commonly listens on ports 80 and 443; MySQL/MariaDB commonly listens on 3306. A MySQL port conflict does not by itself explain an Apache startup failure. If a port is busy, identify the process/service using it before stopping or reconfiguring anything; do not terminate an unknown process or remove database files.

## Create and import the database

For a fresh local setup:

1. In phpMyAdmin, select **New**.
2. Create a database named `airline_reservation` with an `utf8mb4` collation such as `utf8mb4_unicode_ci`.
3. Select `airline_reservation`, open **Import**, and choose [`database/schema.sql`](./database/schema.sql).
4. Run the import and confirm these five tables appear:
   - `users`
   - `airports`
   - `flights`
   - `bookings`
   - `passengers_manifest`
5. In phpMyAdmin's SQL tab, run:

   ```sql
   SHOW TRIGGERS LIKE 'flights';
   ```

   Confirm that `flights_validate_before_insert` and `flights_validate_before_update` are present.

The database already exists in the author's local environment, but each developer must import the schema into their own local database.

## Database schema and relationships

The schema source is [`database/schema.sql`](./database/schema.sql); the entity relationships and foreign-key policies are documented in [`docs/database-erd.md`](./docs/database-erd.md).

| Table | Purpose |
|---|---|
| `users` | Passenger or admin account identity and password hash |
| `airports` | Unique IATA code and airport name/location |
| `flights` | A scheduled flight occurrence, airport references, schedule, capacity, ticket price, and status |
| `bookings` | Account holder, scheduled flight, booking reference, and status |
| `passengers_manifest` | Traveler names attached to a booking; manifest travelers are not necessarily user accounts |

Relationships: an airport can be the origin or destination for many flights; a user can place many bookings; a flight can have many bookings; and a booking can have multiple manifest travelers.

### Flight validation on MariaDB

The schema uses `flights_validate_before_insert` and `flights_validate_before_update` triggers to enforce these rules:

- Origin and destination airports must differ.
- Arrival must be later than departure.
- Seat capacity must be positive.
- New and updated flights must have a positive ticket price; legacy flights may remain unset until edited.

The target XAMPP MariaDB 10.11.16 server rejected the attempted flight `CHECK` constraints with error `#1901` (the `origin_airport_id` expression could not be used in that check). The schema retains the same validation using triggers rather than dropping the rules. Check the actual server's error message: phpMyAdmin's **Static analysis** warnings are separate from the server error shown under **MySQL said**.

## Configure PHP database credentials

[`config/db.php`](./config/db.php) loads `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` from a project-root `.env` file. Process environment values, if configured, take precedence.

| Variable | Local example | Purpose |
|---|---|---|
| `DB_HOST` | `127.0.0.1` | Host running MariaDB |
| `DB_NAME` | `airline_reservation` | Project database |
| `DB_USER` | `airline_app` | MariaDB application account |
| `DB_PASS` | Set to your account's actual non-empty password | MariaDB account password |

To configure a local XAMPP setup:

1. Copy `.env.example` in the project root to a new file named `.env`.
2. Replace its placeholder values with the host, database name, username, and password for your MariaDB account.
3. Keep `.env` local; Git ignores it. Never paste real credentials into source code, README, screenshots, or chat.
4. Reload the page. The PHP configuration reads `.env` on each request; an Apache restart is not normally needed after editing it.

The project uses a small built-in PHP parser; it does not execute `.env` as PHP or require Composer. Blank lines and comments are supported; values may be unquoted or surrounded by single/double quotes. Use quotes for values with spaces or inline comment markers. Environment variables already supplied by Apache/PHP override `.env`. The project [`.htaccess`](./.htaccess) blocks HTTP access to `.env` and `.env.*` files; do not remove this protection. The committed `.env.example` contains placeholders only.

For local development, avoid using the database administrator account in the application. In phpMyAdmin, while signed in as an administrator, create a dedicated account with a non-empty local password (replace the example password before running this SQL):

```sql
CREATE USER 'airline_app'@'127.0.0.1'
    IDENTIFIED BY 'replace-with-a-unique-local-password';
GRANT SELECT, INSERT, UPDATE, DELETE
    ON airline_reservation.*
    TO 'airline_app'@'127.0.0.1';
```

Run the `CREATE USER` statement only once; if the account already exists, use that account or update its password deliberately. Never commit the real password. A local XAMPP installation may have a different account setup; configure the credentials that work for your server.

The current PDO DSN uses the default MySQL port because `config/db.php` has no `DB_PORT` setting. If your server uses a non-default port, update the connection configuration deliberately rather than only changing the XAMPP Control Panel's display/default port setting.

## Test the PDO connection

After creating and configuring the root `.env`, open PowerShell in the project directory and run:

```powershell
& 'C:\xampp\php\php.exe' -m | Select-String '^(PDO|pdo_mysql)$'
& 'C:\xampp\php\php.exe' -r "require 'config/db.php'; echo 'PDO connection OK', PHP_EOL;"
```

The first command should list both `PDO` and `pdo_mysql`. The second should print `PDO connection OK` if CLI PHP can connect using `.env`. This tests CLI PHP; browser requests use Apache's PHP runtime, but both load the same project `.env`. An exception names a missing setting or reports a database/credential/host issue; it never includes credential values. Do not create a publicly accessible diagnostic page that prints credentials.

## Verify the database

In phpMyAdmin, select `airline_reservation` and run:

```sql
SHOW TABLES;
SHOW TRIGGERS LIKE 'flights';
```

Confirm that the five expected tables and both flight validation triggers are present. On the table **Structure** pages, inspect primary/unique keys, indexes, and foreign keys; compare them with [`docs/database-erd.md`](./docs/database-erd.md).

The import has also been tested against a disposable MariaDB 10.11.16 database, including repeat import and rejection of invalid flight inserts/updates. That does not replace checking the import and connection on each developer's own local installation.

## Authentication and sessions

With Apache and MariaDB running and the `DB_*` values configured for Apache/PHP, open:

- Registration: [http://localhost/airline-reservation-system/register.php](http://localhost/airline-reservation-system/register.php)
- Sign in: [http://localhost/airline-reservation-system/login.php](http://localhost/airline-reservation-system/login.php)

Public registration creates passenger accounts only. Passwords must contain at least 8 characters and are limited to 72 UTF-8 bytes; they are stored with PHP's `password_hash()` and login verifies them with `password_verify()`. Forms use CSRF tokens, and successful login regenerates the session ID. The shared header, navbar, and footer are in [`includes/`](./includes/); session and CSRF helpers are in [`config/session.php`](./config/session.php).

The login page accepts both `passenger` and `admin` accounts, but there is intentionally no public admin signup. Admin accounts must be provisioned by a trusted operator through a secure, non-public process and use a `password_hash()` value; do not store passwords or hashes in source control. The navbar reflects the authenticated role and shows the flight dashboard link only to admins.

## Admin flight dashboard

Sign in with a trusted admin account to open [http://localhost/airline-reservation-system/admin-flights.php](http://localhost/airline-reservation-system/admin-flights.php). The dashboard supports creating, viewing, editing, and deleting flights. Guests and passengers are redirected to sign in; only accounts whose stored role is `admin` can access the page.

Flight origin and destination selections come from the existing `airports` table. No airport seed is required: on the admin dashboard, choose **Add airport** and enter its IATA code, airport name, city, and country. Add at least two airports before creating a flight. The **Add a flight** button opens the flight form in a modal. Fares are stored as `DECIMAL(10,2)`; existing flights have no known fare, so they remain `NULL` and display “Price not set” until an admin supplies a positive price.

For an existing database, apply [`database/migrations/06-flight-ticket-price.sql`](./database/migrations/06-flight-ticket-price.sql) in phpMyAdmin against the project database. The additive migration is repeat-safe on the verified MariaDB 10.11.16 target, preserves existing flights and fares, and refreshes the two validation triggers. The updated [`database/schema.sql`](./database/schema.sql) also adds the column before refreshing triggers, so it remains safe to re-import on this MariaDB target. Do not drop tables or re-import to apply the fare change.

Administrators can edit airport codes, names, cities, and countries from the Airports card. Deleting an airport also removes its related flights, but only when none of those flights has a booking. If any booking exists, the entire deletion is refused so reservation history is preserved. Deleting a flight that has bookings is also refused.

### Optional Nigerian demo seed

After confirming `flights.ticket_price` exists, you can optionally select `airline_reservation` in phpMyAdmin, choose **Import**, select [`database/seeds/nigeria-flights.sql`](./database/seeds/nigeria-flights.sql), and run it. The seed includes Nigerian airports, 12 fictional older examples, and 168 rolling schedules across 12 domestic routes for the next 14 days. The rolling schedules are repeat-safe on the same day and do not overwrite existing airport or flight records. The seed is not run automatically by the application. These schedules and fares are fictional demo data, not real airline inventory. You can instead add only the airports and flights you need from the dashboard forms; seed import is optional.

The admin flight list shows 10 flights per page with a result range and accessible page controls. Editing or deleting a flight preserves the current list page.

## Public flight search and passenger reservations

The public landing page lists upcoming scheduled flights with a positive fare and available seats. Visitors can filter flights by route, date, fare, and time. Flight cards show fares in Nigerian naira (`₦`).

Selecting **Book flight** opens a reservation form. A passenger must sign in (or register and then sign in) to reserve 1–6 travelers; the selected flight is retained through that flow. Reservations are saved with `pending` status and one passenger-manifest row per traveler. Seat capacity is checked again inside a transaction before the reservation is created.

This is a reservation request only: the application does not collect payment details, charge a card, or issue a paid/confirmed ticket. Booking history and online payment are not currently implemented.

## Re-import behavior and safety

The schema uses `CREATE TABLE IF NOT EXISTS` so it can fill in missing tables in the current partial setup without dropping existing tables or rows. On the verified MariaDB 10.11.16 target, it also uses `ADD COLUMN IF NOT EXISTS` for `flights.ticket_price` before dropping and recreating its two named validation triggers. Other differences between a pre-existing table and the schema are not automatically reconciled.

`CREATE TABLE IF NOT EXISTS` does **not** update or repair a table that already exists with a different structure. This script does not drop the database or tables. Do not delete the database as an import troubleshooting step unless you have intentionally backed up and chosen to reset your local data.

## Troubleshooting

### XAMPP MySQL will not start (port 3306 is in use)

Another MySQL/MariaDB server may already be listening on port 3306. Identify the owning process/service first. If it is a separate MySQL installation, choose intentionally between using that server or stopping its service (only if you know it is safe to do so) before starting XAMPP's database. Do not stop or kill an unknown process. If changing XAMPP's MySQL port, update the server's actual MySQL configuration and the PHP DSN; changing only a Control Panel port setting does not necessarily change the server listener.

### Apache will not start

Check Apache's own error log and ports 80/443. A web server already bound to those ports can prevent XAMPP Apache from starting. This is separate from the MySQL 3306 conflict.

### phpMyAdmin says “Access denied”

This means the database server rejected the account/password phpMyAdmin tried. Use the credentials configured for the running server and adjust phpMyAdmin's local connection configuration only if you understand the security implications. Starting Apache successfully does not establish a MySQL login.

### PDO reports a missing database environment variable or driver

- Set all four required `DB_*` values for the PHP process making the connection.
- Confirm PHP's `PDO` and `pdo_mysql` extensions are enabled in the same PHP installation used by XAMPP.
- Restart Apache after changing its environment or PHP configuration.
- Remember that a CLI test and an Apache request can run with different environments or PHP configurations.

### Import stops before all tables are created

Read the database server's complete error under **MySQL said**. phpMyAdmin's static-analysis output may be only a parser warning. Check the server engine/version, the first failed `CREATE TABLE`, and whether a previous partial import already created earlier tables. This schema is designed to be re-imported without dropping those tables.

## Current project limitations

This repository currently contains the database/schema foundation, PDO connection setup, passenger registration/login with role-aware sessions, public flight search and pending reservations, and administrator flight management. Booking history and online payment remain future implementation work.
