# Dental Clinic Online

PHP 8 and MySQL appointment, queue, and clinic administration system.

## Features

- Patient registration, email verification, sign-in, and password recovery
- Service/provider selection, live availability, and overlap-safe booking
- Appointment history, cancellation, rescheduling, print-ready receipts, and calendar files
- Patient queue tracking and staff check-in/status controls with 15-second polling
- Staff appointment approval, clinic schedules, breaks, holidays, and booking limits
- Account/role administration, reports, CSV export, audit trail, notices, and SQL backups

## Page Architecture

Patient, staff, and administrator screens use the static HTML shell `app.html`, styled by `assets/css/portal.css` and driven by `assets/js/portal.js`. The supplied clinic photos are used on the landing/sign-in screens and as a lightened background across dashboards and portal features so foreground text and cards remain readable. This includes the role dashboards, patient appointments/booking/queue/alerts/receipts, staff appointments/queue/walk-ins/notification outbox, and admin account management, schedules, reports, audit activity, and backup screen. PHP provides authentication and verification, role checks, validation, database operations, and JSON through `api/auth.php`, `api/portal.php`, `api/availability.php`, and `api/report-export.php`. The calendar download, CSV report, and SQL backup are PHP file responses rather than PHP-rendered screens. Core PHP page URLs redirect to the HTML portal. Queue updates use shared clinic records and refresh automatically.

When the repository is placed at `C:\xampp\htdocs\dental-clinic`, the main portal is at `http://localhost/dental-clinic/dental-clinic/`. If the `dental-clinic/` application folder is deployed directly, open `http://localhost/dental-clinic/`. Test the live queue with designated development accounts and appointments in the local development database; queue check-ins and status changes are real clinic records.

## XAMPP Setup (Windows)

1. Install XAMPP and start **Apache** and **MySQL**.
2. Put this whole project folder in XAMPP's `htdocs` folder. It should be at `C:\xampp\htdocs\dental-clinic`.
3. Open [phpMyAdmin](http://localhost/phpmyadmin), choose **Import**, select `dental-clinic\database\dental_clinic.sql` from this project, then click **Import** at the bottom. This setup keeps existing database records.
4. Double-click `setup-xampp-accounts.bat` in the project folder.
5. Open [the clinic app](http://localhost/dental-clinic/dental-clinic/).

Sign in with one of these local test accounts:

- Admin: `admin@dentalclinic.test` / `Admin@123`
- Staff: `staff@dentalclinic.test` / `Staff@123`
- Patient: `patient@dentalclinic.test` / `Patient@123`

Each coworker should install their own copy and database. This shares code, not patient or appointment data.

## Vercel Setup (Online)

Vercel runs this app with its [Container Runtime (Beta)](https://vercel.com/docs/functions/runtimes/container). The app needs a hosted MySQL database; your computer's XAMPP database cannot be reached from Vercel.

1. Create a hosted MySQL database named `dental_clinic` and import `dental-clinic/database/dental_clinic.sql` using your database provider's instructions.
2. In Vercel, choose **Add New Project** and import this GitHub repository. Keep the project root at the repository root; Vercel uses `Dockerfile.vercel`.
3. In the Vercel project's **Settings > Environment Variables**, add the database host, port, username, and password as `DENTAL_DB_HOST`, `DENTAL_DB_PORT`, `DENTAL_DB_USER`, and `DENTAL_DB_PASS`. Set `DENTAL_DB_NAME` to `dental_clinic`, add `DENTAL_ADMIN_EMAIL` plus a unique `DENTAL_ADMIN_PASSWORD` of at least 8 characters, and set `APP_ORIGIN` to the public site URL for email links.
4. If your database provider gives you a CA certificate, add it as `DENTAL_DB_SSL_CA`. Redeploy after saving the variables.
5. Open the Vercel URL. The first container start creates the administrator account; later starts leave its password unchanged.

Use a new hosted database, not the live XAMPP database. Keep database and admin passwords only in Vercel Environment Variables. Email verification and password-reset delivery need an external mail service and `DENTAL_MAIL_FROM`; scheduled reminders also need an external scheduler. The database backup page downloads a backup but does not store it permanently on Vercel.

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

## Before Public Use

- Use unique production passwords and a secured hosted database.
- Configure and test email delivery, scheduled reminders, and off-site backups.