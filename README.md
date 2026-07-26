# AMIKALE

A lightweight web application for managing products, members, transactions and a simple wallet system.

Features
- Product listing and management
- Member (user) management and authentication
- Exportable transactions
- Basic wallet functionality
- Admin interface
- Dashboard for KPIs

Requirements
- PHP 7.4+ with PDO and mysqli support
- MySQL / MariaDB
- A web server (Apache, Nginx) or PHP built-in server for development

Quick setup
1. Place the project in your web server's document root (or run PHP built-in server):

	php -S localhost:8000

2. Create a database and import the initial schema found in the `db/init.sql` file.

3. Update database credentials in `db.php` to match your environment.

4. Open the site in your browser at `http://localhost:8000` (or your server hostname).

Configuration
- `db.php`: database connection settings — update host, database name, username and password.
- `security.php` and `functions.php`: helper functions and basic security checks; review and customize salts/keys if present.

Usage
- Frontend: `index.php` is the main entry point for users.
- Auth: `signin.php`, `signup.php`, `forgot_password.php`, `reset_password.php` handle authentication flows.
- Amikale: `amikale.php` allows admin to perform sales transactions or account top-ups.
- Export: `export_transactions.php` exports transaction data.
- Wallet: `wallet.php` contains wallet-related endpoints and views.
- Admin: `admin.php` provides administration views (requires admin account).

Project structure (important files)
- `index.php` — public homepage
- `wallet.php` — to view balance
- `amikale.php`	— to perform transactions or top-ups
- `products.php` — product listing and management
- `members.php` — user list and management
- `admin.php` — admin dashboard
- `db.php` — DB connection (configure this)
- `functions.php` — common helper functions
- `security.php` — security related helpers
- `db/init.sql` — database schema and initial data
- `assets/` — CSS, JS and fonts

Database
Import `db/init.sql` into your MySQL/MariaDB server. Verify tables and initial data after import.

Security notes
- Use HTTPS in production and a proper host configuration.
- Do not expose `db.php` or any credentials — keep this file out of public VCS with secrets.
- Review input validation in `functions.php` and `security.php` before production use.

Contributing
- Fork, make changes and submit a pull request. For quick fixes open an issue describing the problem.

Contact
For questions or support, open an issue or contact the repository owner.
