create extension if not exists pgcrypto;

-- Safer employee access flow:
-- 1. Managers invite a work email in public.employee_invites
-- 2. The employee creates their own password through Supabase Auth
-- 3. public.employee_profiles stores the linked auth.users id plus company role/biz info

create table if not exists public.employee_invites (
  email text primary key,
  biz_id text not null,
  company_name text,
  full_name text,
  role text not null default 'staff',
  invite_token text,
  is_active boolean not null default true,
  invited_by text,
  accepted_at timestamptz,
  created_at timestamptz not null default timezone('utc'::text, now())
);

alter table public.employee_invites add column if not exists biz_id text;
alter table public.employee_invites add column if not exists company_name text;
alter table public.employee_invites add column if not exists full_name text;
alter table public.employee_invites add column if not exists role text default 'staff';
alter table public.employee_invites add column if not exists invite_token text;
alter table public.employee_invites add column if not exists is_active boolean not null default true;
alter table public.employee_invites add column if not exists invited_by text;
alter table public.employee_invites add column if not exists accepted_at timestamptz;
alter table public.employee_invites add column if not exists created_at timestamptz not null default timezone('utc'::text, now());

create index if not exists idx_employee_invites_biz_id
  on public.employee_invites (biz_id);

create unique index if not exists idx_employee_invites_token
  on public.employee_invites (invite_token);

create table if not exists public.employee_profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  biz_id text not null,
  email text not null unique,
  full_name text,
  company_name text,
  role text not null default 'staff',
  is_active boolean not null default true,
  invited_at timestamptz,
  created_at timestamptz not null default timezone('utc'::text, now()),
  updated_at timestamptz not null default timezone('utc'::text, now())
);

alter table public.employee_profiles add column if not exists biz_id text;
alter table public.employee_profiles add column if not exists email text;
alter table public.employee_profiles add column if not exists full_name text;
alter table public.employee_profiles add column if not exists company_name text;
alter table public.employee_profiles add column if not exists role text default 'staff';
alter table public.employee_profiles add column if not exists is_active boolean not null default true;
alter table public.employee_profiles add column if not exists invited_at timestamptz;
alter table public.employee_profiles add column if not exists created_at timestamptz not null default timezone('utc'::text, now());
alter table public.employee_profiles add column if not exists updated_at timestamptz not null default timezone('utc'::text, now());

create index if not exists idx_employee_profiles_biz_id
  on public.employee_profiles (biz_id);

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

alter table public.booking_admin_states add column if not exists id uuid default gen_random_uuid();
alter table public.booking_admin_states add column if not exists booking_id text;
alter table public.booking_admin_states add column if not exists biz_id text;
alter table public.booking_admin_states add column if not exists payment_status text default 'Pending';
alter table public.booking_admin_states add column if not exists is_confirmed boolean not null default false;
alter table public.booking_admin_states add column if not exists updated_by text;
alter table public.booking_admin_states add column if not exists updated_at timestamptz not null default timezone('utc'::text, now());

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

create or replace function public.touch_employee_profiles_updated_at()
returns trigger
language plpgsql
as $$
begin
  new.updated_at = timezone('utc'::text, now());
  return new;
end;
$$;

drop trigger if exists trg_employee_profiles_updated_at on public.employee_profiles;

create trigger trg_employee_profiles_updated_at
before update on public.employee_profiles
for each row
execute function public.touch_employee_profiles_updated_at();

create table if not exists public.business_site_visits (
  id uuid primary key default gen_random_uuid(),
  biz_id text not null,
  visitor_token text not null,
  visit_source text,
  visit_date date not null default current_date,
  created_at timestamptz not null default timezone('utc'::text, now()),
  unique (biz_id, visitor_token, visit_date)
);

alter table public.business_site_visits add column if not exists id uuid default gen_random_uuid();
alter table public.business_site_visits add column if not exists biz_id text;
alter table public.business_site_visits add column if not exists visitor_token text;
alter table public.business_site_visits add column if not exists visit_source text;
alter table public.business_site_visits add column if not exists visit_date date not null default current_date;
alter table public.business_site_visits add column if not exists created_at timestamptz not null default timezone('utc'::text, now());

create index if not exists idx_business_site_visits_biz_id
  on public.business_site_visits (biz_id);

-- Seed the first dashboard manager/admin invite:
-- insert into public.employee_invites (email, biz_id, company_name, full_name, role)
-- values ('owner@yourcompany.com', 'your-biz-id', 'Your Rental Company', 'Operations Admin', 'admin');
