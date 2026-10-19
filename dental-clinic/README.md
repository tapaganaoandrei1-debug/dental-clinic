# Dental Clinic Online

PHP 8 and MySQL appointment, queue, and clinic administration system.

## Features

- Patient registration, email verification, sign-in, and password recovery
- Service/provider selection, live availability, and overlap-safe booking
- Appointment history, cancellation, rescheduling, print-ready receipts, and calendar files
- Patient queue tracking and staff check-in/status controls with 15-second polling
- Staff appointment approval, clinic schedules, breaks, holidays, and booking limits
- Account/role administration, reports, CSV export, audit trail, notices, and SQL backups

## XAMPP Setup

1. Start Apache and MySQL in the XAMPP Control Panel.
2. Clone the repository into `htdocs` as `dental-clinic`. The repository contains this app in its `dental-clinic` subfolder; the top-level page routes there automatically.
3. In phpMyAdmin, import `dental-clinic/database/dental_clinic.sql`. The script is repeatable and does not drop existing tables or rows. It creates missing tables and starter services/providers/schedules.
4. From the app folder, initialize the seeded development accounts:

   ```powershell
   Set-Location D:\xampp\htdocs\dental-clinic\dental-clinic
   & D:\xampp\php\php.exe .\scripts\setup-accounts.php
   ```

   Replace `D:\xampp` with your XAMPP installation folder if it differs.

5. Open `http://localhost/dental-clinic/`. The app detects its URL path automatically, including when the repository is nested.

By default, XAMPP connects as MySQL `root` with no password. For another local database account, set `DENTAL_DB_HOST`, `DENTAL_DB_NAME`, `DENTAL_DB_USER`, and `DENTAL_DB_PASS` in the Apache environment, then restart Apache. Set `DENTAL_BASE_URL` only when the app is served through a custom alias. Detailed PHP errors are disabled by default; set `APP_DEBUG=1` only while diagnosing a local development issue.

Each coworker should clone the source and create their own local database. This shares the application code, not accounts or clinic data. For simultaneous use of shared patient and appointment data, deploy one secured central server/database instead of sharing local database files or backups.

## Development Accounts

- Admin: `admin@dentalclinic.test` / `Admin@123`
- Staff: `staff@dentalclinic.test` / `Staff@123`
- Patient: `patient@dentalclinic.test` / `Patient@123`
- Other seeded patients use `Patient@123`.

These passwords are for local development only. Change them before deployment.

## Email, SMS, And Calendar

Set `APP_ORIGIN` to the public site origin (for example `https://clinic.example.com`) and `DENTAL_MAIL_FROM` to a valid sender. Configure PHP `mail()` or a mail transport before deployment. Until then, verification, reset, appointment, and reminder messages remain in the admin-visible outbox; delivery is not guaranteed by the local simulation.

SMS is recorded as an outbox channel only; no SMS gateway is configured. Calendar sync is provided as `.ics` downloads that patients can import into Google Calendar or Outlook; OAuth-based direct synchronization is not configured.

## Scheduled Jobs And Backups

Schedule `scripts/queue-reminders.php` with Windows Task Scheduler to queue due reminder notices. It does not send messages without a configured delivery provider.

Set `MYSQLDUMP_PATH` if `mysqldump.exe` is not at `C:\xampp\mysql\bin\mysqldump.exe`. For scheduled backups, set `DENTAL_BACKUP_DIR` to a protected folder and schedule:

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\dental-clinic\scripts\backup-database.php
```

Backups are SQL files. Restrict the backup folder and test restoration before relying on it.

## Before Deployment

- Remove or protect development-only demo accounts and replace local database credentials.
- Use HTTPS, a strong database password, and a production mail transport.
- Remove or protect development-only demo accounts and rotate every default password.
- Configure and test SMTP/SMS delivery, scheduled jobs, and database backups.