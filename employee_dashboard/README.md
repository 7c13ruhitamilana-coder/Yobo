# Employee Dashboard

This folder contains a separate employee-only website for the rental company team.

Quick setup:

1. Run the SQL in `employee_dashboard/supabase_schema.sql` inside Supabase.
2. Generate a password hash with `python employee_dashboard/hash_password.py`.
3. Insert at least one row into `public.employee_users` using your company `biz_id`.
4. Start the dashboard locally with `python -m employee_dashboard.app`.

Required environment variables:

- `SUPABASE_URL`
- `SUPABASE_SERVICE_KEY` or `SUPABASE_KEY`
- `DASHBOARD_SECRET_KEY`
