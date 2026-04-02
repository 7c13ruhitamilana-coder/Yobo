create extension if not exists pgcrypto;

create table if not exists public.employee_users (
  id uuid primary key default gen_random_uuid(),
  biz_id text not null,
  username text not null unique,
  password_hash text not null,
  full_name text,
  company_name text,
  is_active boolean not null default true,
  created_at timestamptz not null default timezone('utc'::text, now())
);

create index if not exists idx_employee_users_biz_id
  on public.employee_users (biz_id);

create table if not exists public.booking_admin_states (
  id uuid primary key default gen_random_uuid(),
  booking_id text not null,
  biz_id text not null,
  payment_status text not null default 'Pending',
  is_confirmed boolean not null default false,
  updated_by text,
  updated_at timestamptz not null default timezone('utc'::text, now()),
  unique (booking_id, biz_id)
);

create index if not exists idx_booking_admin_states_biz_id
  on public.booking_admin_states (biz_id);

create or replace function public.touch_booking_admin_states_updated_at()
returns trigger
language plpgsql
as $$
begin
  new.updated_at = timezone('utc'::text, now());
  return new;
end;
$$;

drop trigger if exists trg_booking_admin_states_updated_at on public.booking_admin_states;

create trigger trg_booking_admin_states_updated_at
before update on public.booking_admin_states
for each row
execute function public.touch_booking_admin_states_updated_at();

-- Generate a password hash locally with:
-- python employee_dashboard/hash_password.py
--
-- Then insert your first employee record:
-- insert into public.employee_users (biz_id, username, password_hash, full_name, company_name)
-- values ('your-biz-id', 'ops_admin', 'paste-generated-hash-here', 'Operations Admin', 'Your Rental Company');
