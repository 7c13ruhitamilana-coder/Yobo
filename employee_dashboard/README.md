# Employee Dashboard

This folder contains a separate employee-only website for the rental company team.

Quick setup:

1. Run the SQL in `employee_dashboard/supabase_schema.sql` inside Supabase.
2. Insert at least one row into `public.employee_invites` for the first manager or admin using your company `biz_id`.
3. Have that invited person open `/register` and create their own password through Supabase Auth.
4. Start the dashboard locally with `python -m employee_dashboard.app`.

Required environment variables:

- `SUPABASE_URL`
- `SUPABASE_SERVICE_KEY`
- `SUPABASE_ANON_KEY` or `SUPABASE_PUBLISHABLE_KEY`
- `DASHBOARD_SECRET_KEY`

Notes:

- The old `employee_users` + `password_hash` flow is no longer the recommended path.
- Password reset and optional email confirmation are now handled by Supabase Auth.
- If you use email confirmation or recovery emails, set your Supabase Auth site URL / redirect URLs to the employee dashboard domain.
