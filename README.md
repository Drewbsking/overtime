# Overtime Portal (PHP)

Secure overtime request and equalization starter built with PHP 8.1+, MySQL, PHPMailer, and phpdotenv.

## Features
- Admin-managed accounts only; temp passwords require reset on first login.
- Users can request expiring email password reset links from the login page.
- Submit overtime with date, hours, and reason; lifecycle: pending to approved/denied.
- Email notifications on submission/decision to the requestor and any active Admin/Approver marked to receive OT emails.
- Equalization board ranks lowest approved hours over the last 365 days.
- CSRF protection, prepared statements, secure sessions, and env-based secrets.

## Setup
1) **Install dependencies** (on host with Composer):
   ```bash
   composer install
   ```
2) **Configure env**:
   - Copy `.env.example` to `.env` and fill DB + SMTP settings.
   - Set `APP_KEY` to a random 32+ character string.
3) **Create database**:
   - Import `database/schema.sql` into your MySQL database.
   - Existing installs should add the password reset table:
     ```sql
     CREATE TABLE password_resets (
         id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
         user_id INT UNSIGNED NOT NULL,
         token_hash CHAR(64) NOT NULL UNIQUE,
         expires_at DATETIME NOT NULL,
         used_at DATETIME NULL,
         created_at DATETIME NOT NULL,
         CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

     CREATE INDEX idx_password_resets_user ON password_resets(user_id);
     CREATE INDEX idx_password_resets_expires ON password_resets(expires_at);
     ```
4) **Create the first admin** (CLI only):
   ```bash
   php bin/create_admin.php admin you@example.com
   ```
   - Note the temp password; admin must reset on first login.
5) **Deploy**:
   - Point your document root to `public/` (or move `public/*` into the web root and keep `src/`, `vendor/`, `database/` outside web access).
   - Ensure `.htaccess` is honored to block direct access to non-public folders.

## SMTP
Configure your SMTP server (`SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM_*`, optional `SMTP_ENCRYPTION`). Messages fall back to logging when PHPMailer is missing or SMTP fails (`storage/logs/mail.log`).

Forgot-password links use `APP_URL` to build absolute URLs and expire after `PASSWORD_RESET_MINUTES` minutes, defaulting to 60.

## Notification Recipients
Admins can toggle "OT emails" for Admin/Approver accounts on the Users page. Those opted-in users receive request/decision emails alongside the requestor. (Database column: `users.notify_on_request`.)

## Equalization (CSV-driven)
- Place your equalization CSV file at the path in `.env` (`EQUALIZATION_FILE`), default `storage/equalization.csv`.
- Expected format: first cell (row 1, col 1) may contain an "as of" note/date when the rest of the row is empty. Extra columns are ignored. Export your sheet as CSV and upload/replace; the board reads the file on each load.
- Supported legacy format: name in column D; overtime hours = column J (regular YTD) + column Q (double YTD).
- Supported new format (2026-02-06+): number in column A, name in column B; overtime hours = column D (regular YTD) + column G (double YTD). If YTD is blank, current hours from columns C and F are used.

## Security Defaults
- `DISPLAY_ERRORS=false` in production; errors log to `storage/logs/app.log`.
- CSRF tokens on every POST; sessions are HTTP-only and SameSite=Lax.
- Passwords hashed with Argon2id when available (else bcrypt).

## Equalization Window
The board sums approved hours over the last **365 days** (change in `public/equalization.php` or `Overtime::equalizationBoard`).
