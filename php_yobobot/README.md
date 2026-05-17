# PHP Yobobot

This folder contains the PHP version of the Yobobot app:

- main marketing site
- portal account creation and login
- onboarding and workspace flow
- public booking-site pages
- customer-facing booking APIs
- employee dashboard routes

## Render-ready notes

This app is now prepared to run on Render with:

- environment-variable based secrets
- a configurable persistent app data directory
- Docker deployment support

Use these environment variables on Render:

- `SUPABASE_URL`
- `SUPABASE_SERVICE_KEY`
- `SUPABASE_ANON_KEY`
- `GEMINI_API_KEY`
- `GEMINI_MODEL`
- `DASHBOARD_SECRET_KEY`
- `PRIMARY_SITE_DOMAIN`
- `APP_DATA_DIR`
- `SESSION_COOKIE_SECURE`

Important:

- `APP_DATA_DIR` should point to a mounted persistent disk path on Render, for example `/var/data/yobobot`.
- The app still stores portal users and dashboard branding as JSON files, but now it writes them into `APP_DATA_DIR` instead of assuming your local repo.
- If you do not set `APP_DATA_DIR`, the app falls back to local storage. That is fine for development, not ideal for production.

## Run locally

```bash
cd /Users/nisha/Documents/Playground/php_yobobot
SSL_CERT_FILE=/etc/ssl/cert.pem php -S 127.0.0.1:8610 -t public public/index.php
```

If `php` is not on your PATH yet:

```bash
cd /Users/nisha/Documents/Playground/php_yobobot
SSL_CERT_FILE=/etc/ssl/cert.pem /opt/homebrew/Cellar/php/8.5.6/bin/php -S 127.0.0.1:8610 -t public public/index.php
```

## Important notes

- The app reads Supabase and Gemini keys from `/Users/nisha/Documents/Playground/secrets.toml`.
- Legacy portal accounts from the old Python app are supported. On the first successful login, their old Werkzeug password hash is automatically re-saved in the new PHP password format.
- Shared business/platform configuration is currently stored in `config/platform_data.json`.

## Deploy on Render

1. Push this folder to GitHub.
2. In Render, create a new `Web Service`.
3. Choose `Docker` as the runtime.
4. Set the service root directory to `php_yobobot`.
5. Attach a persistent disk and mount it at `/var/data`.
6. Add these environment variables:

```text
APP_DATA_DIR=/var/data/yobobot
PRIMARY_SITE_DOMAIN=yobobot.in
SESSION_COOKIE_SECURE=1
SUPABASE_URL=...
SUPABASE_SERVICE_KEY=...
SUPABASE_ANON_KEY=...
GEMINI_API_KEY=...
GEMINI_MODEL=gemini-2.5-flash
DASHBOARD_SECRET_KEY=...
```

7. Deploy and test:

- `/`
- `/create-account`
- `/account/login`
- `/dashboard/login`
- one business subdomain such as `https://veep.yobobot.in`
