# Bayanihan Connect

**Donation and Volunteer Management System for Bayanihan Outreach Foundation**
PCCBSIT 006 – Application Development and Emerging Technologies · Group 10

Bayanihan Connect replaces separate coordinator spreadsheets, finance-only donation logs, and group-chat volunteer
coordination with one role-based system. Every donation is linked to the activity and beneficiaries it reached, so
donor reports and the Director's consolidated report are generated automatically.

## Technology (per the A2 design)

| Layer | Technology |
|---|---|
| Front-end | HTML / CSS / JavaScript (Blade templates, no build step), responsive down to entry-level phones |
| Back-end | Laravel 12 (PHP 8.2+) |
| Database | MySQL / MariaDB (XAMPP) |
| Offline support | Progressive Web App: service worker + on-device queue with sync-on-reconnect |

## Running it locally (XAMPP)

1. Start **MySQL** in the XAMPP Control Panel.
2. Create the database (once):
   ```bash
   C:/xampp/mysql/bin/mysql.exe -uroot -e "CREATE DATABASE IF NOT EXISTS bayanihan_connect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   ```
3. From this folder, install and load the demo data:
   ```bash
   composer install
   php artisan migrate:fresh --seed
   ```
   (On a fresh copy, also run `cp .env.example .env` and `php artisan key:generate` first.)
4. Start the server and open http://localhost:8000:
   ```bash
   php artisan serve
   ```

## Demo accounts

All demo accounts use the password **`password`** (change them before any real use).

| Role | Email | What they see |
|---|---|---|
| Administrator | admin@bayanihan.test | Everything, plus units-of-measure settings |
| Director | director@bayanihan.test | Dashboards, all records (read), consolidated reports, users, audit log |
| Project Coordinator | coordinator@bayanihan.test | Beneficiaries, distributions, programs/activities, volunteer scheduling, messages |
| Finance Staff | finance@bayanihan.test | Donors, donations, distributions (read), reports. **No beneficiary data.** |
| Volunteer | volunteer@bayanihan.test | Own schedule, open-slot sign-up, messages |
| Donor (view-only portal) | donor@bayanihan.test | Own donations and where they went (counts only, no beneficiary names) |

## Functional requirements → where they live

| FR | Requirement | Implementation |
|---|---|---|
| FR01 | Register / update beneficiaries | `BeneficiaryController` – required-field validation, duplicate guard (same name + barangay), shared `BN-00001` IDs |
| FR02 | Record donations linked to a project/activity | `DonationController` – reference number `DN-YYYY-0001`; activity must belong to the chosen program |
| FR03 | Log distributions against funding donations | `Services/DistributionRecorder` – checks remaining balance (row-locked), same program, same unit; asks for another source on shortfall |
| FR04 | Volunteer sign-ups and schedules | `Services/VolunteerScheduler` – rejects overlapping time slots and full activities; volunteers self sign-up at *My Schedule* |
| FR05 | Standardized units for in-kind goods | `units` table + required dropdown; admins manage the list under *Settings* |
| FR06 | Consolidated cross-program reports | `Services/ReportBuilder` + `ReportController` – date range + program filter, CSV export, print/PDF; prompts to widen range when empty |
| FR07 | Offline capture with sync | `public/sw.js`, `public/js/app.js` – forms marked `data-offline` queue on the device, replay with a `client_uuid` so nothing is saved twice |
| FR08 | Role-based access | `Support/Role` permission matrix → gates on every route; denied attempts and all record changes go to the **Audit Log** |
| FR09 | In-app messaging | `MessageController` – conversations, optional activity link, read receipts; empty messages rejected |

The consultation revision is reflected too. Distribution, funding donation, and beneficiaries are one linked entry
form, and dashboard shortcut cards put every entry form two steps from login.

## Tests

```bash
php artisan test
```

31 feature tests (306 assertions) cover each functional requirement plus a check that every screen opens for the
roles allowed to see it, and is refused (403) for everyone else. Tests run on an in-memory SQLite database and never
touch the MySQL data.

## Offline use in the field

Open the app once while online (the dashboard, *Register Beneficiary*, *Log Distribution*, and *Messages* screens are
saved to the device). Without a signal, submissions on those forms are stored on the phone and shown as
"pending". They are sent automatically when the connection returns. Saved screens are cleared on logout. Service
workers need `localhost` or HTTPS, so deploy behind HTTPS for phones to use offline mode.
