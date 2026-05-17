# PHP Employee Dashboard Port

This folder contains a parallel PHP rewrite of the employee dashboard stack. It leaves the Python app untouched and uses the same Supabase tables plus the same dashboard JS/CSS interaction model.

## Included

- Employee login, register, recover, logout
- Invite-based employee access with Supabase Auth
- Dashboard overview
- Bookings API and booking status updates
- Availability board and calendar API
- Fleet management API
- Customization API
- Bot intake question editor with a 10-question cap

## Expected Environment Variables

- `SUPABASE_URL`
- `SUPABASE_SERVICE_KEY`
- `SUPABASE_ANON_KEY` or `SUPABASE_PUBLISHABLE_KEY`
- `DASHBOARD_SECRET_KEY`

The PHP app also falls back to `../secrets.toml` for these values if present.

## Local Run

Use PHP's built-in server from this folder:

```bash
cd php_employee_dashboard
php -S 127.0.0.1:8600 -t public public/index.php
```

If your PHP install still points to a broken Homebrew certificate path, start it like this instead:

```bash
cd php_employee_dashboard
SSL_CERT_FILE=/etc/ssl/cert.pem php -S 127.0.0.1:8600 -t public public/index.php
```

Then open:

```text
http://127.0.0.1:8600/login
```

## Notes

- `public/static/dashboard.css` and `public/static/dashboard.js` are copied from the current dashboard assets.
- `supabase_schema.sql` is copied from the current employee dashboard schema.
- This workspace does not currently have `php` installed, so the PHP app could not be executed or linted locally here after generation.
