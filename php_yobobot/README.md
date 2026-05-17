# PHP Yobobot

This folder contains the PHP version of the Yobobot app:

- main marketing site
- portal account creation and login
- onboarding and workspace flow
- public booking-site pages
- customer-facing booking APIs
- employee dashboard routes

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
