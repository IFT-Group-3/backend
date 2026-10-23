# Hostel backend

## API documentation

The complete OpenAPI 3.0 specification for the clean API routes is in
[`openapi.yaml`](./openapi.yaml). Open it in Swagger Editor or import it into
Swagger UI. To view and try the endpoints in your browser using the bundled
Swagger UI page, visit `http://localhost/hostel-backend/docs/`. The
specification defaults to the hosted API at `https://hostel-system.iceiy.com`;
select the local XAMPP server in Swagger UI when testing locally. Update the
server list in `openapi.yaml` for other environments. Protected endpoints use
the PHP session cookie set by `POST /api/auth/login`.

Authentication uses PHP sessions. Students and admins sign in through the same
endpoint; the account's `role` in the `users` table determines which middleware
allows access.

The local frontend at `http://localhost:8000` is allowed by credentialed CORS
on the API front controller. Browser requests must use `credentials: "include"`
(or `withCredentials: true`) so the browser stores and sends the session
cookie. The cookie is host-only, has path `/`, and uses `SameSite=Lax` and
`HttpOnly`; `Secure` is enabled for HTTPS and disabled for local HTTP.
`localhost` on ports 8000 and 80 is same-site, so `SameSite=Lax` works for this
local setup.

The local database defaults are XAMPP's `127.0.0.1`, database `hostel_system`,
user `root`, and an empty password. To override these, copy
`config/database.example.php` to `config/database.local.php` and enter the local
database settings, or set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` in
the PHP server environment. Environment variables take precedence over the
local file. `config/database.local.php` is git-ignored and the `config/`
directory must not be exposed by the web server. Ensure MySQL is running and
the configured database and schema exist. On connection failure, API requests
return JSON with HTTP 500; the detailed PDO error is written to the PHP server
error log, not returned to the client.

## Hostel flow

- To add sample facilities for API testing without removing existing data,
  import [`database/seed_api_test_data.sql`](./database/seed_api_test_data.sql)
  in phpMyAdmin with `hostel_system` selected. It adds one mixed hostel, two
  four-bed rooms priced at NGN 1,000 per occupant, their beds, and a 90-day
  allocation period only if one is not already configured. It can be run again;
  duplicate rooms and beds are skipped, and an existing allocation period is
  left unchanged. It does not create student accounts, reservations, or
  payments. Register a student through `POST /api/auth/register` so the
  password is stored using the application's password hashing.
- This seed expects the current schema, including the hostel, bed-space, and
  allocation-period tables. Do not run destructive migrations just to add test
  data: migrations `001` and `004` delete existing student data and existing
  allocations/payments respectively.
- `GET /api/admin/dashboard` returns overview counts and paginated hostels,
  rooms, beds, students, admin accounts, allocations, payments, and the current
  allocation period. Requires an admin session. Optional query parameters:
  `page` (default `1`, maximum `1000000`) and `per_page` (default `50`, maximum
  `100`); pagination applies to each returned collection. For example,
  `/api/admin/dashboard?page=2&per_page=100` requests the next page. The
  top-level response fields are `success`, `allocation_period`, `summary`,
  `pagination`, `hostels`, `rooms`, `beds`, `students`, `admins`, `allocations`,
  and `payments`. `pagination` contains `page`, `per_page`, and `totals`, which
  reports full row counts keyed by collection (`bed_spaces` is the count for
  the `beds` array). Fetch subsequent pages by incrementing `page`; each
  collection contains at most `per_page` records. Password hashes are never
  included.
- `POST /api/admin/hostels` creates a hostel and requires an admin session.
  Submit `multipart/form-data` fields `hostel_name`, `hostel_type` (`Male`,
  `Female`, or `Mixed`), `location`, optional `description`, optional
  `total_rooms` (defaults to `0`), optional `status` (defaults to `Available`),
  and optional image file field `image`. Images may be JPEG, PNG, or WebP up to
  5 MB; the returned `image_path` is relative to the project.
- `GET /api/hostels` requires a student session and returns available hostels
  matching the student's registered gender plus all available `Mixed` hostels.
  Unavailable hostels are not shown.
- `POST /api/admin/rooms` creates a room under an existing hostel. JSON fields:
  `hostel_id`, `building_code` (for example `C`), `floor_number` (for example
  `17`; use `0` for ground floor), `room_number` (for example `C2`),
  `max_occupants`, and `price_per_occupant` in NGN. The building/floor/room
  combination must be unique within a hostel. Creating rooms generates one
  bed space per occupant (for example `C-17-C2-BED-0001`) and updates that
  hostel's `total_rooms` count. `max_occupants` is limited to 1-1000.

```json
{
  "hostel_id": 1,
  "building_code": "C",
  "floor_number": 17,
  "room_number": "C2",
  "max_occupants": 4,
  "price_per_occupant": "75000.00"
}
```

- `GET /api/rooms/availability` requires a student session and lists rooms in
  gender-eligible hostels, including current occupied and available spaces and
  available bed numbers for the configured allocation dates. Full rooms are
  excluded.
- `PUT /api/admin/allocation-period` configures the single system-wide
  allocation window with `{"start_date":"2027-01-10","end_date":"2027-05-31"}`.
  Dates use `YYYY-MM-DD`; start must be today or later and end must not precede
  start. Configure this before students browse or reserve rooms.
- `POST /api/allocations/create` accepts `{"room_id": 1}` and assigns a numbered
  bed for the configured start/end dates. It holds the bed for 30 minutes while
  awaiting payment. Confirmed allocations and unexpired pending holds block
  only that bed for overlapping allocation dates.
- `POST /api/payments/process` accepts `{"allocation_id": 1}` and returns a
  Paystack checkout URL for the per-occupant price.
- After checkout, `POST /api/payments/verify` accepts the returned
  `{"reference": "hostel_..."}`. The backend verifies it directly with Paystack
  and confirms the allocation only if payment status, amount, and NGN currency
  match.
- If Paystack confirms a payment after its 30-minute reservation has expired,
  the API reports `refund_required: true`; refund processing is not automated.
- Configure `PAYSTACK_SECRET_KEY` in the PHP server environment before using
  checkout. If your host does not allow environment variables, copy
  `config/secrets.example.php` to `config/secrets.php` on the server and replace
  its placeholder with your rotated Paystack secret key. `config/secrets.php`
  is git-ignored, and direct HTTP access to the `config/` directory is denied.
  Do not put the key in frontend code, a public `.env` file, or the repository.
- Run `database/migrations/002_create_hostels_table.sql` first (if not already
  applied), then `database/migrations/003_create_rooms_allocations_payments.sql`,
  followed by `database/migrations/004_add_bed_spaces_and_allocation_dates.sql`
  and `database/migrations/005_add_building_and_floor_to_rooms.sql` in
  phpMyAdmin with `hostel_system` selected. Migration 004 clears existing
  allocations and payments because they have no bed assignment or allocation
  dates; it keeps students, hostels, and rooms and generates the beds for
  existing rooms. Migration 005 assigns existing rooms to Building A, Floor 0;
  update those values in the database to their actual locations if needed.

Hostel creation is a `multipart/form-data` request so the optional `image`
field can carry a file. Keep the session cookie from admin login and send it
with the request. Uploaded files are stored under `uploads/hostels/`; the
database stores their relative path in `image_path`.

## Login and logout routes

- `POST /api/auth/bootstrap-admin` creates the first admin only, and only when
  the request comes from localhost. It returns HTTP 409 once any admin account
  exists. To create a working admin, call it once from the machine running
  Apache with an email and a unique password of 8-72 characters, then use those
  credentials with `POST /api/auth/login`. Bootstrap does not log the account
  in. Remove the bootstrap route and endpoint file after initial setup. This
  repository does not provide a default admin password.
- `POST /api/auth/register` creates a student account. Required JSON fields are
  `email`, `password`, `matric_number`, `first_name`, `last_name`, `department`,
  `level`, and `gender` (`Male` or `Female`). `phone` is optional. Passwords
  must be 8-72 characters. Registration always creates a `student` role.

```json
{
  "email": "student@example.com",
  "password": "securepass123",
  "matric_number": "STU001",
  "first_name": "Alex",
  "last_name": "Student",
  "phone": "08012345678",
  "department": "Computer Science",
  "level": 200,
  "gender": "Female"
}
```
- `POST /api/auth/admin/register` creates an admin account and accepts `email`
  and `password`. The caller must already be signed in as an admin; send its
  session cookie with the request. Passwords must be 8-72 characters.
- `POST /api/auth/login` accepts JSON containing `email` and `password`.
- On login success, the response includes the user ID, email, and role. Keep
  the session cookie and send it with subsequent requests.
- `POST /api/auth/logout` destroys the current session.

When using Apache with `mod_rewrite` enabled, these clean routes are dispatched
through `index.php`. The direct PHP URLs (`/api/auth/bootstrap-admin.php`,
`/api/auth/register.php`,
`/api/auth/register-admin.php`, `/api/auth/login.php`, and
`/api/auth/logout.php`) remain available as well. For local requests, use the
project's URL prefix, for example
`http://localhost/hostel-backend/api/auth/register`.

Passwords in `users.password` are stored as PHP `password_hash()` hashes.

The updated `database/schema.sql` defines the full student profile table,
including its `user_id` link to the login account. It is used when setting up a
new database. To migrate the old development schema, back up the database and
import `database/migrations/001_update_students_table.sql` in phpMyAdmin while
`hostel_system` is selected. This migration deletes all existing student
accounts and profiles; admin accounts are preserved. Do not run it on a
database whose student records need to be kept.

## Protecting API endpoints

Include the appropriate middleware at the start of each protected endpoint:

```php
require_once __DIR__ . "/../../middlewares/student.php";
```

Use `middlewares/admin.php` for admin-only endpoints and `middlewares/auth.php`
for endpoints shared by both roles. Unauthenticated requests receive HTTP 401;
an authenticated user with the wrong role receives HTTP 403. The middleware
files handle starting the configured session.