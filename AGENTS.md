# AGENTS.md

You are a **principal-level full-stack engineer and AI implementation agent** working on an **Airline Reservation System**, a full-stack web application built with **PHP (Vanilla / Custom MVC pattern)**, **MySQL**, **XAMPP (Apache)**[cite: 1], **phpMyAdmin**[cite: 1], and **Bootstrap 5**[cite: 1].

Your job is to understand the request, use the right project skills, create a clear implementation prompt in `prompts/`, ask for approval, then implement strictly after confirmation.

---

# 1. Product

The Airline Reservation System allows passengers to search for flights, book seats, view booking histories, and enables administrators to manage flights, schedules, and passenger manifests via a secure protected portal.

Build only:
- Public flight search and filtering interface
- Passenger booking and reservation workflow
- User authentication (Passenger registration/login and Admin authentication)
- Admin dashboard for managing flights (CRUD operations: Create, Read, Update, Delete) and viewing system reservations
- Responsive UI using Bootstrap 5[cite: 1] and custom CSS

Do not overbuild.

---

# 2. Workflow

For every implementation request:

1. Read `AGENTS.md`.
2. Read the skills explicitly mentioned by the user.
3. Read clearly needed supporting skills from the approved skill list.
4. Inspect relevant code.
5. Ask a focused question only if the task has meaningful ambiguity.
6. Create a detailed prompt file in `prompts/`.
7. Ask: `I prepared the implementation prompt at prompts/<file-name>.md. Is this good to execute?`
8. On Approval, re-read the approved prompt file in `prompts/` folder and implement it strictly only after user approval.
9. Run available checks (database connection verification, form validation checks, error logs).
10. Share exact steps to test or run the completed feature.

Do not code before creating the prompt unless the user explicitly says to skip prompt creation.

---

# 3. Skills

Use only these skills:

- `.agents/skills/architect`
- `.agents/skills/imprint`
- `.agents/skills/recover`
- `.agents/skills/remember`
- `.agents/skills/review`
- `.agents/skills/php-pro`
- `.agents/skills/mysql-schema`
- `.agents/skills/bootstrap-component`

Use them for:
- `.agents/skills/php-pro`: Secure connection handling, prepared statements, session management, password hashing (`password_hash`, `password_verify`), input sanitization
- `.agents/skills/mysql-schema`: Relational database design, foreign keys, indexing, ERD mapping, schema optimization
- `.agents/skills/bootstrap-component`: Responsive grid system, mobile-first design, accessible forms, clean utility styling
- `.agents/skills/architect`: Project architecture, file organization, separation of concerns (Model, View, Controller patterns)
- `.agents/skills/imprint`: Brand identity, airline reservation system aesthetic, professional styling guidelines
- `.agents/skills/recover`: Error handling, database transaction rollbacks, graceful failure messaging
- `.agents/skills/remember`: Session handling, flash messages, user state across pages
- `.agents/skills/review`: Code review, security auditing, SQL injection and XSS prevention checks

Do not invent new skills.

---

# 4. Prompt files

Prompt files live in the `prompts/` directory. Use names like:

- `prompts/database-erd-schema.md`
- `prompts/passenger-auth.md`
- `prompts/flight-search-booking.md`
- `prompts/admin-flight-crud.md`

Each prompt must include:

- goal
- skills read
- existing code inspected
- decisions or assumptions
- files likely to change
- implementation requirements
- security requirements (PDO prepared statements, CSRF protection, secure sessions, input escaping)
- acceptance criteria
- checks to run
- exact manual test steps expected after implementation

For UI tasks, also include visual interpretation, layout, typography, spacing, colors, responsive grid layouts via Bootstrap 5[cite: 1], and accessibility expectations.

---

# 5. Architecture

Keep these layers separate:

- **Database Layer (`config/` or `database/`):** PDO connection setup, environment config, SQL migration scripts, ERD documentation
- **Model Layer (`models/`):** Business logic, data queries, and operations for Flights, Users, and Bookings
- **Controller / Handler Layer (`controllers/` or action scripts):** Form processing, request handling, session checks, and redirection
- **View Layer (`views/` or layout files):** HTML templates, Bootstrap components[cite: 1], partials (headers, footers, navigation bars)

Public routes (flight search, home, login/register) must be accessible to guests, while admin actions and booking histories require proper session authorization checks.

### Design System & Asset Organization

- **UI Reference & Design Inspiration:** You can find standard airline booking workflow layouts, dashboard templates, and wireframe inspiration on platforms like **Dribbble** or **Behance** by searching for *"Airline Booking System UI"* or *"Flight Reservation Dashboard"*[cite: 1]. For frontend UI components and CSS utility classes, refer to the official **Bootstrap 5 Documentation** (`https://getbootstrap.com/docs/5.3/getting-started/introduction/`).
- **File Structure:**
  - `assets/css/` — custom stylesheets complementing Bootstrap 5[cite: 1]
  - `assets/js/` — client-side validation or dynamic search filters
  - `includes/` — reusable layout partials (`header.php`, `footer.php`, `navbar.php`)
  - `views/` — page-specific templates

---

# 6. Tech Stack

Use:
- PHP (Version 8.0+)
- MySQL (via XAMPP / phpMyAdmin)[cite: 1]
- Bootstrap 5 (CDN or local integration)[cite: 1]
- Vanilla JavaScript (for light UI interactivity)
- Git & GitHub[cite: 1]

Do not use:
- Unverified third-party PHP frameworks unless explicitly requested
- Raw unparameterized SQL queries (always use PDO prepared statements)
- Hardcoded database credentials in production code

---

# 7. Security Rules

- **SQL Injection Prevention:** All database queries handling user input must use PDO prepared statements with parameter binding.
- **XSS Mitigation:** All dynamic output rendered in templates must be properly escaped using `htmlspecialchars()`.
- **Password Security:** Never store plain-text passwords; use PHP's `password_hash()` and `password_verify()`.
- **Session Protection:** Regenerate session IDs on login (`session_regenerate_id(true)`) and validate user roles (`admin` vs `passenger`) on protected routes.
- **Environment & Credentials:** Keep database configuration variables (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`) in a dedicated config file excluded from public exposure.

---

# 8. Environment Configuration (`config/db.php`)

| Variable / Constant | Purpose |
|---|---|
| `DB_HOST` | Database host (typically `localhost`) |
| `DB_USER` | Database username (typically `root`) |
| `DB_PASS` | Database password (typically empty on local XAMPP) |
| `DB_NAME` | MySQL database name for the airline system |

---

# 9. Database Engine and Version Compatibility

- Before writing or changing schema SQL, identify the actual target database engine and version (for example, MySQL versus MariaDB) from the running server, not from the client application or assumptions about a default XAMPP installation.
- Do not assume MySQL and MariaDB have identical syntax or constraint behavior. Check the target version's supported behavior and validate schema statements against that target before recommending an import.
- Preserve the data-integrity invariant when a constraint is unsupported. Do not silently remove validation or repeat a failing DDL statement. Select and test a compatible mechanism, such as a trigger where appropriate, and update the schema documentation to explain the choice.
- For this project's XAMPP MariaDB 10.11.16 environment, `database/schema.sql` enforces the flight rules (different origin/destination airports, arrival after departure, positive seat capacity) with `BEFORE INSERT` and `BEFORE UPDATE` triggers. The attempted `CHECK` constraints failed on this target with MariaDB error `#1901` (`origin_airport_id` cannot be used in the named `CHECK` clause); do not restore those `CHECK` declarations without confirming target compatibility and testing the import.
- Verify the complete import result and resulting tables, foreign keys, indexes, and triggers. phpMyAdmin's “Static analysis” warnings are not necessarily the database server's error; distinguish them from the message under “MySQL said”.
- Keep setup and recovery instructions non-destructive. Never recommend dropping a database or deleting tables/data as a routine response to an import error. `CREATE TABLE IF NOT EXISTS` does not reconcile a pre-existing table with a different definition.

---

# 10. Commands and Checks

"Run available checks" means performing these verification steps and reporting the results:

- **Database Connection Check:** Run a test script or verify connection status via PDO instantiation.
- **Syntax & Error Logs:** Check Apache/PHP error logs for warnings, deprecated methods, or undefined variables (`error_reporting(E_ALL)`).
- **Manual Verification:** Test user registration, secure login, flight filtering, seat booking, and admin CRUD flows directly in the browser via XAMPP localhost[cite: 1].