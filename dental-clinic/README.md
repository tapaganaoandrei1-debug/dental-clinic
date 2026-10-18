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
2. Place this folder at `C:\xampp\htdocs\dental-clinic`.
3. For a new database, import `database/dental_clinic.sql` in phpMyAdmin. This script drops and recreates the project tables; do not run it against data you need to keep.
4. For an existing database, import `database/phase3_migration.sql`. It adds email verification and password recovery without dropping clinic data; it is safe to rerun.
5. Check `config/database.php` and `BASE_URL` in `config/config.php` if your folder name or MySQL credentials differ.
6. For the seeded development accounts, run from a terminal:

   ```powershell
   C:\xampp\php\php.exe C:\xampp\htdocs\dental-clinic\scripts\setup-accounts.php
   ```

Open `http://localhost/dental-clinic/` after placing the project under `htdocs`.

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

- Set `DEBUG_MODE` to `false` and replace the XAMPP database credentials.
- Use HTTPS, a strong database password, and a production mail transport.
- Remove or protect development-only demo accounts and rotate every default password.
- Configure and test SMTP/SMS delivery, scheduled jobs, and database backups.