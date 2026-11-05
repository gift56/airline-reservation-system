# Airline Reservation System

A PHP and MariaDB project scaffold for an airline reservation system. The current repository provides the database foundation and its documentation: a PDO connection entry point, a MySQL/MariaDB schema for accounts, airports, scheduled flights, bookings, and passenger manifests, and an ERD.

> **Current status:** The database schema has been imported successfully into the author's local XAMPP MariaDB setup. The passenger-facing website and admin portal are not implemented yet. There are currently no flight-search, registration/login, booking, booking-history, or flight-management pages to test.

## Contents

- [Technology and verified local environment](#technology-and-verified-local-environment)
- [Start the local XAMPP services](#start-the-local-xampp-services)
- [Create and import the database](#create-and-import-the-database)
- [Database schema and relationships](#database-schema-and-relationships)
- [Configure PHP database credentials](#configure-php-database-credentials)
- [Test the PDO connection from PowerShell](#test-the-pdo-connection-from-powershell)
- [Verify the database](#verify-the-database)
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
| `flights` | A scheduled flight occurrence, airport references, schedule, capacity, and status |
| `bookings` | Account holder, scheduled flight, booking reference, and status |
| `passengers_manifest` | Traveler names attached to a booking; manifest travelers are not necessarily user accounts |

Relationships: an airport can be the origin or destination for many flights; a user can place many bookings; a flight can have many bookings; and a booking can have multiple manifest travelers.

### Flight validation on MariaDB

The schema uses `flights_validate_before_insert` and `flights_validate_before_update` triggers to enforce these rules:

- Origin and destination airports must differ.
- Arrival must be later than departure.
- Seat capacity must be positive.

The target XAMPP MariaDB 10.11.16 server rejected the attempted flight `CHECK` constraints with error `#1901` (the `origin_airport_id` expression could not be used in that check). The schema retains the same validation using triggers rather than dropping the rules. Check the actual server's error message: phpMyAdmin's **Static analysis** warnings are separate from the server error shown under **MySQL said**.

## Configure PHP database credentials

[`config/db.php`](./config/db.php) reads these variables using PHP's `getenv()`:

| Variable | Local example | Purpose |
|---|---|---|
| `DB_HOST` | `127.0.0.1` | Host running MariaDB |
| `DB_NAME` | `airline_reservation` | Project database |
| `DB_USER` | `airline_app` | MariaDB application account |
| `DB_PASS` | Set to your account's actual non-empty password | MariaDB account password |

**A `.env` file at the project root is not automatically read.** This project has no dotenv loader or Composer dependency. Make the variables available to the PHP process that needs them. For browser requests, configure the environment for Apache/PHP using your local Windows/XAMPP setup and restart Apache after changing it. Variables set only in a PowerShell window are available to CLI commands launched from that window; they do not configure Apache.

For local development, avoid using the database administrator account in the application. In phpMyAdmin, while signed in as an administrator, create a dedicated account with a non-empty local password (replace the example password before running this SQL):

```sql
CREATE USER 'airline_app'@'127.0.0.1'
    IDENTIFIED BY 'replace-with-a-unique-local-password';
GRANT SELECT, INSERT, UPDATE, DELETE
    ON airline_reservation.*
    TO 'airline_app'@'127.0.0.1';
```

Run the `CREATE USER` statement only once; if the account already exists, use that account or update its password deliberately. Never commit the real password. The current [`config/db.php`](./config/db.php) requires `DB_PASS` to be present. On Windows, assigning an empty value to a PowerShell environment variable removes it, and PHP then sees `getenv('DB_PASS')` as missing. Use a database account with a non-empty password for this configuration.

The current PDO DSN uses the default MySQL port because `config/db.php` has no `DB_PORT` setting. If your server uses a non-default port, update the connection configuration deliberately rather than only changing the XAMPP Control Panel's display/default port setting.

## Test the PDO connection from PowerShell

Open PowerShell in the project root (`C:\xampp\htdocs\airline-reservation-system`) and set temporary environment variables for that terminal. Adjust the username/password to match your local server:

```powershell
$env:DB_HOST = '127.0.0.1'
$env:DB_NAME = 'airline_reservation'
$env:DB_USER = 'airline_app'
$env:DB_PASS = 'replace_with_the_password_you_created'

& 'C:\xampp\php\php.exe' -m | Select-String '^(PDO|pdo_mysql)$'
& 'C:\xampp\php\php.exe' -r "require 'config/db.php'; echo 'PDO connection OK', PHP_EOL;"
```

Replace the example password with the non-empty password you chose. The first command should list both `PDO` and `pdo_mysql`. The second should print `PDO connection OK` if the CLI PHP runtime can connect with the supplied values. An exception indicates a missing environment variable, a database/credential/host issue, or a missing PHP extension; read the message and check the matching runtime configuration.

These assignments are temporary and apply only to this PowerShell process and its child commands. This test checks **CLI PHP**, not whether Apache has the same environment variables. Do not create a publicly accessible diagnostic page that prints credentials.

## Verify the database

In phpMyAdmin, select `airline_reservation` and run:

```sql
SHOW TABLES;
SHOW TRIGGERS LIKE 'flights';
```

Confirm that the five expected tables and both flight validation triggers are present. On the table **Structure** pages, inspect primary/unique keys, indexes, and foreign keys; compare them with [`docs/database-erd.md`](./docs/database-erd.md).

The import has also been tested against a disposable MariaDB 10.11.16 database, including repeat import and rejection of invalid flight inserts/updates. That does not replace checking the import and connection on each developer's own local installation.

## Re-import behavior and safety

The schema uses `CREATE TABLE IF NOT EXISTS` so it can fill in missing tables in the current partial setup without dropping existing tables or rows. It also drops and recreates only its two named flight-validation triggers so the trigger definitions can be refreshed.

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

This repository currently contains the database/schema foundation and PDO connection setup. The product features listed in [`AGENTS.md`](./AGENTS.md)—flight search, passenger registration/login, reservations, booking history, and admin flight/reservation pages—remain future implementation work. The root URL `http://localhost/airline-reservation-system/` is not yet a completed application entry point.
