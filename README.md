# Travel Platform - PHP + MySQL

This project is a PHP 8 + MySQL travel platform using PDO. It supports public trip browsing, planner trip publishing and insights, traveler interactions, admin operations reporting, and SMTP email notifications.

## Requirements

- PHP 8.0 or newer
- PHP extensions: `filter`, `pdo`, `pdo_mysql`, `session`
- MySQL 8.0 or MariaDB 10.4 or newer
- Apache with `mod_rewrite`, Nginx + PHP-FPM, or the PHP built-in server for local testing
- Composer, required for PHPMailer SMTP delivery

Check PHP extension availability:

```bash
php -m | grep -E "filter|PDO|pdo_mysql|session"
```

If Composer is available, validate the platform requirements:

```bash
composer check-platform-reqs
```

## Important Deployment Rule

The web server document root must point to the `public/` directory.

Correct:

```text
/path/to/T-P-ver-php/public
```

Incorrect:

```text
/path/to/T-P-ver-php
```

The homepage should be opened from the domain root:

```text
https://example.com/
```

Do not deploy the site as:

```text
https://example.com/public/index.php
```

All in-app links intentionally use paths such as `/index.php`, `/assets/app.css`, and `/actions/login.php`. Those paths work when `public/` is the web root.

## Page Map

Public pages:

- `/` or `/index.php`: homepage, public trips, and public planners
- `/trip.php?id=...`: trip detail page
- `/planner.php?id=...`: planner public profile
- `/login.php`: login page
- `/register.php`: registration page

After login:

- `traveler` users go to `/traveler-dashboard.php`
- `planner` users go to `/planner-dashboard.php`
- `admin` users go to `/admin-dashboard.php`

Planner workflow:

- `/planner-dashboard.php` lists planner trips and stats
- `/editor.php` creates a trip
- `/editor.php?id=...` edits a trip
- Trip forms submit to `/actions/trip-save.php`

Traveler workflow:

- Travelers can join trips, favorite trips/planners, and review joined trips
- Forms submit to `/actions/participation.php`, `/actions/favorite-trip.php`, `/actions/favorite-planner.php`, and `/actions/review.php`

Admin workflow:

- `/admin-dashboard.php` manages users and recent reviews
- Forms submit to `/actions/admin-user-role.php` and `/actions/admin-delete-review.php`

## Why `router.php` Exists

The browser requests action URLs like:

```text
/actions/login.php
```

But the real `actions/` directory is outside `public/`:

```text
actions/login.php
```

This keeps action handlers out of the public document root. Requests for `/actions/*.php` are routed through `public/router.php`, which safely includes the matching file from the project-level `actions/` directory.

## Ubuntu Apache Setup

Example virtual host:

```apache
<VirtualHost *:80>
    ServerName example.com
    DocumentRoot /path/to/T-P-ver-php/public

    <Directory /path/to/T-P-ver-php/public>
        AllowOverride All
        Require all granted
        DirectoryIndex index.php
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/travel-platform-error.log
    CustomLog ${APACHE_LOG_DIR}/travel-platform-access.log combined
</VirtualHost>
```

Enable rewrite support if it is not already enabled:

```bash
sudo a2enmod rewrite
sudo systemctl reload apache2
```

The project includes `public/.htaccess`, which sends missing files such as `/actions/login.php` to `router.php`.

## Ubuntu Nginx + PHP-FPM Setup

Example server block:

```nginx
server {
    listen 80;
    server_name example.com;

    root /path/to/T-P-ver-php/public;
    index index.php;

    location ^~ /actions/ {
        rewrite ^ /router.php last;
    }

    location / {
        try_files $uri $uri/ /router.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\. {
        deny all;
    }
}
```

Adjust the PHP-FPM socket path to match your Ubuntu version, for example `php8.2-fpm.sock` or `php8.3-fpm.sock`.

## Database Setup

Import the schema:

```bash
mysql -u root -p < migrations/schema.sql
```

The default database name is:

```text
travel_platform_db
```

Database connection values can be configured with environment variables:

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

Copy the example environment file before local development:

```bash
cp .env.example .env
```

Then edit `.env` to match your local MySQL account. If `.env` and environment variables are not set, the app uses the defaults from `config/database.php`.

Phase 3 SMTP and scheduling settings:

```dotenv
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Taipei
PLANNER_DIGEST_ANCHOR_DATE=2026-05-27
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=mailer@example.com
MAIL_PASSWORD=replace-with-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=mailer@example.com
MAIL_FROM_NAME=Travel Platform
```

Never commit production SMTP credentials.

For an existing Phase 2 database, run the additive Phase 3 migration once:

```bash
mysql -u root -p travel_platform_db < migrations/phase3_operations.sql
```

## Composer Scripts

Install third-party PHP dependencies before serving or running email tasks:

```bash
composer install
```

`composer.json` documents the required PHP version/extensions and exposes useful scripts:

```bash
composer run serve
composer run seed
composer run lint
```

`composer run serve` starts the same local server as the manual command below.

## Seed Data

After importing the schema, run:

```bash
php seed.php
```

Seeded accounts use this password:

```text
password123
```

Accounts:

| Role | Email |
| --- | --- |
| Admin | `admin@example.com` |
| Traveler | `traveler@example.com` |
| Planner | `planner@example.com` |
| Planner | `planner2@example.com` |

## Local PHP Built-In Server

For local testing:

```bash
php -S localhost:8000 -t public public/router.php
```

Open:

```text
http://localhost:8000/
```

## Phase 3 Email Tasks

Email delivery uses SMTP through PHPMailer. Registration welcome messages and new-device login alerts are attempted during the user request. An SMTP failure does not block authentication; it is written to email delivery logs and surfaced as a non-blocking message.

Scheduled CLI tasks:

```bash
php scripts/send-daily-admin-digest.php
php scripts/send-planner-three-day-digest.php
php scripts/send-winback-emails.php
php scripts/send-daily-popular-digest.php
```

The first three commands run at `09:00`; the popular digest runs at `17:00`. All dates are interpreted in `Asia/Taipei`.

Windows Task Scheduler program and arguments:

```text
Program: C:\xampp\php\php.exe
Arguments: C:\path\to\project\scripts\send-daily-admin-digest.php
Arguments: C:\path\to\project\scripts\send-planner-three-day-digest.php
Arguments: C:\path\to\project\scripts\send-winback-emails.php
Arguments: C:\path\to\project\scripts\send-daily-popular-digest.php
```

Create separate tasks for each command at its required time.

Linux cron example:

```cron
0 9 * * * php /path/to/project/scripts/send-daily-admin-digest.php
0 9 * * * php /path/to/project/scripts/send-planner-three-day-digest.php
0 9 * * * php /path/to/project/scripts/send-winback-emails.php
0 17 * * * php /path/to/project/scripts/send-daily-popular-digest.php
```

## Deployment Checklist

- Confirm the web root is `public/`, not the project root.
- Confirm `/` loads the homepage.
- Confirm `/index.php` loads the homepage.
- Confirm `/assets/app.css` loads.
- Confirm `/login.php` and `/register.php` load.
- Confirm POST requests to `/actions/*.php` do not return 404.
- Run syntax checks where PHP CLI is available:

```bash
php -l public/router.php
php -l public/index.php
php -l actions/login.php
```

For a full syntax pass:

```bash
find . -name "*.php" -print0 | xargs -0 -n1 php -l
```
