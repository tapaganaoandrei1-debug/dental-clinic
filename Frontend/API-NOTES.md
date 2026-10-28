# API notes for the backend

The frontend calls these endpoints when `USE_FAKE_DATA = false` in `js/config.js`.
The same contract is written at the top of each page script, so check there if something is unclear.

## Rules for every endpoint

- **JSON in, JSON out.** Every response has `"ok": true` or `"ok": false` with `"error": "message for the user"`.
  A form error on one field can add `"field": "email"`.
- **Cookies/sessions.** The frontend sends `credentials: "include"`, so use PHP sessions and answer CORS with the exact frontend
  origin (not `*`), plus `Access-Control-Allow-Credentials: true`, `Access-Control-Allow-Headers: Content-Type`, and reply to `OPTIONS`.
  Use `localhost` everywhere or `127.0.0.1` everywhere, not a mix.
- **Check the role on the server.** Hiding a button in the frontend is not security.
  Patient pages: `patient`. Staff pages: `staff` or `admin`. Admin pages: `admin`.
  A patient must only ever see their own appointments, receipts, and queue entries.
- **Dates and times:** dates `YYYY-MM-DD`, times `HH:MM` (seconds are accepted). The clinic is in Asia/Manila.
- **Statuses.** Roles: `patient`, `staff`, `admin`. Appointment: `pending`, `confirmed`, `completed`, `cancelled`, `no_show`.
  Queue: `waiting`, `called`, `serving`, `completed`, `skipped`, `no_show`.
- **Business rules.** Patients can only cancel or reschedule `pending`/`confirmed` visits that are more than 2 hours away
  (send `can_change` so the server decides). Bookable up to 60 days ahead. Lunch 12:00-13:00. Slots every 30 minutes.
  New accounts must verify their email before logging in. Passwords: at least 8 characters with a letter and a number.

## Endpoints

| # | Method and URL | Who | Sends | Returns |
|---|---|---|---|---|
| 1 | `POST login.php` | anyone | `{email, password}` | `{ok, user:{id,name,email,role}}` |
| 2 | `POST register.php` | anyone | `{name, email, phone, password}` | `{ok}` or `{ok:false, field:"email", error}` |
| 3 | `POST logout.php` | any | - | `{ok}` |
| 4 | `POST forgot-password.php` | anyone | `{email}` | `{ok:true}` always (do not reveal if the account exists) |
| 5 | `POST reset-password.php` | anyone | `{token, password}` | `{ok}` or `{ok:false, error}` |
| 6 | `GET verify-email.php?token=` | anyone | - | `{ok, email}` or `{ok:false, error}` |
| 7 | `POST verify-email.php` | anyone | `{token}` | `{ok}` or `{ok:false, error}` |
| 8 | `GET services.php` | any | - | `{ok, services:[{id,name,category,description,duration_minutes,price}]}` (active only) |
| 9 | `GET dentists.php` | any | - | `{ok, dentists:[{id,name,specialty,service_ids:[1,5]}]}` |
| 10 | `GET availability.php?service_id=&dentist_id=&date=` | patient | `dentist_id=0` means any | `{ok, slots:[{time:"09:00", available:true}]}` include booked times with `available:false` |
| 11 | `GET queue-summary.php` | patient | - | `{ok, now_serving:"Q-009", people_ahead:4, estimated_wait_minutes:25, next_queue_number:"Q-014"}` |
| 12 | `POST book-appointment.php` | patient | `{service_id, dentist_id, date, time, notes}` | `{ok, appointment_id}` or `{ok:false, error}`. `dentist_id` 0 = assign one |
| 13 | `GET my-appointments.php` | patient | - | `{ok, appointments:[{id,date,start,end,status,service_name,dentist_name,price,notes,cancellation_reason,can_change}], notifications:[{subject,message,created_at}]}` |
| 14 | `GET appointment.php?id=` | patient | - | `{ok, appointment:{id,service_id,dentist_id,date,start,end,status,notes,can_change}}` (used by reschedule) |
| 15 | `POST cancel-appointment.php` | patient | `{appointment_id, reason}` | `{ok}` or `{ok:false, error}` |
| 16 | `POST reschedule-appointment.php` | patient | `{appointment_id, dentist_id, date, time, notes}` | `{ok}` or `{ok:false, error}` |
| 17 | `GET queue-status.php` | patient | - | `{ok, entries:[{id,queue_number:14,status,start_time,service_name,dentist_name,ahead_count,wait_estimate,now_serving}], notifications:[...]}` (today, active entries) |
| 18 | `GET alerts.php` | patient | - | `{ok, notifications:[{id,type,subject,message,created_at}], next_appointment:{id,date,start,end,service_name,dentist_name}|null}` |
| 19 | `GET receipt.php?id=` | patient | - | `{ok, clinic_name, appointment:{id,date,start,end,status,service_name,dentist_name,price,patient_name,email,created_at,cancellation_reason}}` |
| 20 | `GET calendar.php?id=` | patient | - | a `.ics` file (optional) |
| 21 | `GET staff-queue.php` | staff/admin | - | `{ok, entries:[{id,queue_number,patient_name,service_name,dentist_name,status,start_time,waiting_minutes}], check_ins:[{appointment_id,start,patient_name,service_name,status}], stats:{in_queue,now_serving,avg_wait_minutes,no_shows}}` |
| 22 | `POST queue-action.php` | staff/admin | `{action:"call"|"start"|"serve"|"skip"|"no_show", id}`, `{action:"call_next"}`, `{action:"check_in", appointment_id}` | `{ok}` or `{ok:false, error}` |
| 23 | `GET staff-dashboard.php` | staff/admin | - | `{ok, stats:{appointments_today,confirmed,pending,cancelled}, schedule:[{start,patient_name,service_name,status}]}` |
| 24 | `GET staff-appointments.php` | staff/admin | - | `{ok, appointments:[{id,date,start,end,status,patient_name,phone,service_name,dentist_name,notes}]}` (latest 200) |
| 25 | `POST appointment-action.php` | staff/admin | `{id, action:"confirm"|"cancel"|"complete"|"no_show"}` | `{ok}` or `{ok:false, error}` |
| 26 | `POST walk-in.php` | staff/admin | `{name, phone, service_id, dentist_id, notes}` | `{ok, queue_number}` (creates a confirmed appointment for now and a waiting queue entry) |
| 27 | `GET staff-notifications.php` | staff/admin | - | `{ok, notifications:[{id,recipient,type,subject,message,status,created_at}]}` |
| 28 | `POST send-reminders.php` | staff/admin | - | `{ok, sent}` (tomorrow's pending/confirmed appointments) |
| 29 | `GET admin-users.php` | admin | - | `{ok, users:[{id,name,email,phone,role,is_active,email_verified}]}` |
| 30 | `POST admin-user-action.php` | admin | `{action:"set_role", id, role}`, `{action:"set_active", id, active}`, `{action:"create", name, email, role, password}` | `{ok}` or `{ok:false, error, field?}`. Refuse changes to the signed-in admin's own role or active status |
| 31 | `GET/POST admin-schedules.php` | admin | POST `{dentist_id, days:[1..6], start, end}` | GET `{ok, dentists:[{id,name,specialty,days,start,end}]}` (0 = Sunday) |
| 32 | `GET admin-reports.php?days=7` | admin | `days` 7 or 30 | `{ok, totals:{appointments,completed,cancelled,no_show,revenue}, by_day:[{date,count}], by_service:[{name,count}]}` (`by_day` has one row per day, oldest first) |
| 33 | `GET/POST admin-settings.php` | admin | POST `{name, phone, email, address}` | GET `{ok, settings:{name,phone,email,address}}` |
| 34 | `GET admin-backup.php` | admin | - | downloads the backup file |

## Where the existing PHP project helps

The GitHub PHP project already has the database (`database/dental_clinic.sql`) and the logic for most of these actions
(booking, availability, queue transitions, check-in, notifications). Most endpoints are the same code returning JSON instead of a page.

## Test order that works

1. CORS + `login.php` and `logout.php` (log in as each role).
2. `services.php`, `dentists.php`, `availability.php`, `book-appointment.php`.
3. `my-appointments.php`, `cancel-appointment.php`, `reschedule-appointment.php`, `receipt.php`, `alerts.php`.
4. `staff-queue.php`, `queue-action.php`, `queue-status.php`, `queue-summary.php`.
5. Staff pages, then admin pages.

Debug with `F12` > **Network**: a red request shows the status code and the response body.
