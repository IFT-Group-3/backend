# Hostel backend authentication

Authentication uses PHP sessions. Students and admins sign in through the same
endpoint; the account's `role` in the `users` table determines which middleware
allows access.

## Login and logout routes

- `POST /api/auth/bootstrap-admin` creates the first admin only, and only when
  the request comes from localhost. It returns HTTP 409 once any admin account
  exists. Use it once from the machine running Apache, then remove the
  bootstrap route and endpoint file.
- `POST /api/auth/register` creates a student account. Required JSON fields are
  `email`, `password`, `matric_no`, and `name`; `department` and `level` are
  optional. Passwords must be 8-72 characters. Registration always
  creates a `student` role.
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

## Protecting API endpoints

Include the appropriate middleware at the start of each protected endpoint:

```php
require_once __DIR__ . "/../../middlewares/student.php";
```

Use `middlewares/admin.php` for admin-only endpoints and `middlewares/auth.php`
for endpoints shared by both roles. Unauthenticated requests receive HTTP 401;
an authenticated user with the wrong role receives HTTP 403. The middleware
files handle starting the configured session.