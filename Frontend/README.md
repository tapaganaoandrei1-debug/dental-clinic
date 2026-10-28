# Dental Clinic Online - Frontend

Plain HTML, CSS, and JavaScript (with Bootstrap for forms and buttons). It follows the group's teal UI/UX design.
It runs **without a backend** in demo mode, and connects to the PHP/MySQL backend by changing one file.

## Run it (demo mode)

1. Open this folder in VS Code.
2. Install the **Live Server** extension.
3. Right-click `login.html` and choose **Open with Live Server**.

Demo accounts (password in brackets):

| Role | Email | Password |
|---|---|---|
| Patient | patient@dentalclinic.test | Patient@123 |
| Staff | staff@dentalclinic.test | Staff@123 |
| Admin | admin@dentalclinic.test | Admin@123 |

Tip for the demo: book a visit for today as the patient, check them in as staff (Queue Control > Arriving today), then watch the patient's Live Queue page.

Demo data is saved in the browser only. To reset it: F12 > Application > Local Storage > Clear.

## Folder map

- `css/style.css` - all styles, colors are variables at the top (`:root`)
- `js/config.js` - **the only file to edit to connect the backend**
- `js/fake-data.js` - sample data for demo mode, plus shared helpers (keep this file)
- `js/auth.js` - login, sign up, password reset, email verification, session helpers
- `js/layout.js` - header and footer for public and patient pages
- `js/ops-layout.js` - sidebar for staff and admin pages
- `js/*.js` (one per page) - page logic, each starts with a comment listing the API it needs

## Pages

- Public: `index`, `login`, `register`, `forgot-password`, `reset-password`, `verify-email`
- Patient: `book`, `appointments`, `my-queue`, `alerts`, `receipt`
- Staff: `staff-dashboard`, `staff-queue`, `staff-appointments`, `staff-walk-in`, `staff-notifications`
- Admin: `admin-dashboard`, `admin-users`, `admin-schedules`, `admin-reports`, `admin-backup` (plus the staff pages)

## Connect the backend (turn off the fake data)

1. Open `js/config.js` and change:
   ```js
   const USE_FAKE_DATA = false;
   const API_URL = "http://localhost/dental-api";   // the real API address
   ```
2. Build the endpoints listed in **`API-NOTES.md`** (the same contract is in the comment at the top of each page script).
   Every response is JSON with `"ok": true/false` and, on failure, `"error": "message"`.
3. The requests send cookies, so PHP must allow the frontend's exact address (not `*`):
   ```php
   header("Access-Control-Allow-Origin: http://127.0.0.1:5500");
   header("Access-Control-Allow-Credentials: true");
   header("Access-Control-Allow-Headers: Content-Type");
   ```
   and answer the browser's `OPTIONS` request. Use `localhost` everywhere or `127.0.0.1` everywhere, not a mix.
4. Do not delete `js/fake-data.js`. Its date and money helpers are used by every page. Only the `fake...` functions stop being used.

Roles are `patient`, `staff`, `admin`. Queue statuses: `waiting`, `called`, `serving`, `completed`, `skipped`, `no_show`.
Appointment statuses: `pending`, `confirmed`, `completed`, `cancelled`, `no_show`.
