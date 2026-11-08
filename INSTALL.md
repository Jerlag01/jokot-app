# Installing jokot-app on a new web server

This guide installs the JH Jokot website and admin site (Laravel 13 + Filament 5) on a fresh Linux server
with nginx, PHP-FPM and MySQL/MariaDB. The commands assume Ubuntu 24.04 or Debian 12 and the app in
`/var/www/jokot-app`; adjust paths, domain and user names to your server.

> Moving the existing site instead of starting empty? Read [Moving an existing installation](#moving-an-existing-installation)
> first: you need the same `APP_KEY`, the database dump and the private files.

## 1. Requirements

| Component | Version / notes |
|---|---|
| PHP | 8.4 (8.3 is the minimum) with PHP-FPM |
| PHP extensions | bcmath, ctype, curl, dom, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, tokenizer, xml, zip |
| Database | MySQL 8 or MariaDB 10.6+, `utf8mb4` |
| Web server | nginx (Apache works too via `public/.htaccess`) |
| Composer | 2.x |
| Node.js | not needed (no Vite build; CSS/JS in `public/` are hand-written) |
| Outgoing HTTPS | Stripe, SumUp, Billit, download.db-ip.com (country database) and the SMTP server |

```bash
sudo apt update
sudo apt install nginx mysql-server unzip git \
  php8.4-fpm php8.4-cli php8.4-bcmath php8.4-curl php8.4-gd php8.4-intl \
  php8.4-mbstring php8.4-mysql php8.4-xml php8.4-zip
# Composer: https://getcomposer.org/download/
```

If your distribution has no PHP 8.4 packages, add the `ppa:ondrej/php` repository (Ubuntu) or `packages.sury.org` (Debian) first.

Recommended PHP-FPM settings (`/etc/php/8.4/fpm/php.ini`):

```ini
memory_limit = 256M          ; PDF generation (invoices, contracts, member cards)
upload_max_filesize = 100M   ; scans of incoming invoices, insurance policies
post_max_size = 100M
date.timezone = Europe/Brussels
```

## 2. Database

```sql
CREATE DATABASE jokot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'jokot'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
GRANT ALL PRIVILEGES ON jokot.* TO 'jokot'@'localhost';
FLUSH PRIVILEGES;
```

Use the web server's host instead of `localhost` when the database runs on another machine.

## 3. Get the code

```bash
sudo mkdir -p /var/www && cd /var/www
sudo git clone <repository-url> jokot-app      # or copy the folder (without vendor/ and .env)
cd jokot-app
sudo composer install --no-dev --optimize-autoloader
```

`vendor/`, `.env`, uploaded files and the country database are not in git.

## 4. Configuration (`.env`)

```bash
sudo cp .env.example .env
sudo php artisan key:generate
```

Then edit `.env`. The values that matter:

```dotenv
APP_NAME="JH Jokot"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://www.jokot.be        # used in mails, contract links, member-card QR codes and webhooks
APP_TIMEZONE=Europe/Brussels
APP_LOCALE=nl
APP_FALLBACK_LOCALE=nl
APP_FAKER_LOCALE=nl_BE

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=jokot
DB_USERNAME=jokot
DB_PASSWORD="choose-a-strong-password"

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync               # mails are sent immediately, no queue worker needed
FILESYSTEM_DISK=local

MAIL_MAILER=log                     # leave as is: SMTP is set in the admin site (Instellingen → E-mail)
```

> **Keep `APP_KEY` safe and never change it on a running site.** It encrypts the Stripe, SumUp, Billit and
> SMTP secrets stored in the database. With another key those secrets can no longer be read and must be
> entered again.

## 5. Install the database and files

```bash
cd /var/www/jokot-app
sudo php artisan migrate --force
sudo php artisan db:seed --force      # site pages, roles, products, contract templates, expense categories
sudo php artisan storage:link         # public uploads (event images, logo) under /storage
sudo php artisan geoip:update         # country database for login attempts and visitor statistics
sudo php artisan jokot:admin you@example.com --name="Your name"   # asks for a password
```

Optional one-time imports (only on a fresh database, they add Jokot's existing figures):

```bash
sudo php artisan db:seed --class=AnnualAccountsImportSeeder --force   # "Jaarrekening in progress.xlsx" (2024-2026)
sudo php artisan db:seed --class=PricingImportSeeder --force          # "Factorberekening JH Niel.xlsx"
```

Cache the configuration for production (repeat after every change to `.env` or the code):

```bash
sudo php artisan optimize
sudo php artisan filament:optimize
```

### File permissions

PHP-FPM runs as `www-data` and must be able to write to `storage/` and `bootstrap/cache/`:

```bash
sudo chown -R www-data:www-data /var/www/jokot-app
sudo find /var/www/jokot-app/storage /var/www/jokot-app/bootstrap/cache -type d -exec chmod 775 {} \;
```

If PHP-FPM is sandboxed by systemd (`ProtectSystem=strict`, as on the current server), allow those two folders:

```bash
sudo mkdir -p /etc/systemd/system/php8.4-fpm.service.d
sudo tee /etc/systemd/system/php8.4-fpm.service.d/jokot-app-writable.conf >/dev/null <<'EOF'
[Service]
ReadWritePaths=/var/www/jokot-app/storage /var/www/jokot-app/bootstrap/cache
EOF
sudo systemctl daemon-reload && sudo systemctl restart php8.4-fpm
```

## 6. nginx

`/etc/nginx/sites-available/jokot-app`:

```nginx
server {
    listen 80;
    server_name www.jokot.be jokot.be;
    return 301 https://www.jokot.be$request_uri;
}

server {
    listen 443 ssl;
    http2 on;
    server_name www.jokot.be;

    root /var/www/jokot-app/public;
    index index.php;
    charset utf-8;

    access_log /var/log/nginx/jokot.access.log;
    error_log  /var/log/nginx/jokot.error.log;

    client_max_body_size 100m;

    ssl_certificate     /etc/letsencrypt/live/www.jokot.be/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/www.jokot.be/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;

    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header Referrer-Policy strict-origin-when-cross-origin;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~* \.(?:ico|css|js|jpe?g|png|svg|webp|woff2?)$ {
        try_files $uri /index.php?$query_string;
        expires 30d;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/jokot-app /etc/nginx/sites-enabled/
sudo certbot --nginx -d www.jokot.be -d jokot.be     # Let's Encrypt certificate
sudo nginx -t && sudo systemctl reload nginx
```

Do **not** add `X-Robots-Tag none` (the dev server has it): the public site must be indexed by search engines.

Visitor IP addresses are used for IP blocking and the country lookup. If the site runs behind a reverse proxy
or Cloudflare, configure Laravel's trusted proxies so the real client IP is seen, otherwise every visitor
gets the proxy's address.

## 7. Scheduler (cron)

Several tasks run on a schedule. Without this cron job unpaid ticket orders are never released and the
Billit status, country database and log clean-up do not run.

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/jokot-app && php artisan schedule:run >> /dev/null 2>&1
```

| Task | When | What |
|---|---|---|
| `tickets:expire-orders` | every 5 minutes | releases tickets of unpaid online orders |
| `billit:sync` | hourly | delivery/payment status from Billit, incoming invoices |
| `logs:prune` | daily 03:40 | removes old login attempts and page views |
| `geoip:update` | monthly | refreshes the DB-IP country database |

Check with `php artisan schedule:list`.

## 8. Settings in the admin site

Log in at `https://<domain>/admin` with the account from step 5 and go through **Systeem → Instellingen**:

1. **Algemeen**: name, address, e-mail, company number, social media, membership fee.
2. **E-mail**: SMTP server, then use "Testmail versturen". "E-mails versturen" switches all mail off (only the
   test mail still goes out); below it each automatic mail can be switched off separately. The texts of the
   mails are edited in **Systeem → E-mailsjablonen**.
3. **Verhuur**: booking window, notification address, weekdays without requests, and whether confirming a
   rental or cup loan mails the contract at once (rentals: off by default, send it via "Contract").
4. **Facturatie**: IBAN/BIC, VAT number and VAT note, payment term.
5. **Tickets & betalingen**:
   - Stripe: secret key, and a webhook in the Stripe dashboard to `https://<domain>/webhooks/stripe`
     with the `checkout.session.*` events; paste its signing secret.
   - SumUp: API key and merchant code (no webhook to configure; the return URL is sent with each payment).
6. **Billit & Doccle**: API key and environment, "Billit testen", then "Billit-webhook registreren".
7. **Beveiliging**: add your own IP addresses to "Nooit blokkeren" so you cannot lock yourself out.

Then create users and give them a role (**Systeem → Gebruikers**): Admin, Editor, Financieel, Evenementen, Check-in.

## Moving an existing installation

To move the current site (e.g. from devjokot.jl-tech.be) with all its data:

1. Put the old site in maintenance mode: `php artisan down`.
2. Dump the database: `mysqldump --single-transaction jokot > jokot.sql` and import it on the new server
   instead of running `migrate`/`db:seed` (run `php artisan migrate --force` afterwards for newer migrations).
3. Copy `.env` (at least the **same `APP_KEY`**) and adjust `APP_URL`, database and debug settings.
4. Copy the uploaded files:
   - `storage/app/private/` — signed contracts, purchase-invoice scans, insurance policies
   - `storage/app/public/` — event images, logo, page images
5. Follow steps 5 (from `storage:link`), 6, 7 and the permissions section.
6. Update the Stripe webhook URL and re-register the Billit webhook for the new domain.

## Updating

```bash
cd /var/www/jokot-app
sudo php artisan down
sudo git pull
sudo composer install --no-dev --optimize-autoloader
sudo php artisan migrate --force
sudo php artisan optimize && sudo php artisan filament:optimize
sudo chown -R www-data:www-data .
sudo systemctl reload php8.4-fpm      # clears OPcache
sudo php artisan up
```

Back up the database before every update that contains migrations.

## Running the tests (development only)

The test suite needs the dev dependencies (`composer install` without `--no-dev`) and a separate database
whose name ends in `_test` (for example `jokot_test`, configured in `phpunit.xml`). `tests/TestCase.php`
refuses to run against any other database, so the live data can never be wiped. Run with `php artisan test`.

## Troubleshooting

| Problem | Solution |
|---|---|
| Locked out ("Toegang geweigerd.") | `php artisan security:unblock <your-ip>` (or `--all`), then whitelist your IP |
| Forgot the admin password | `php artisan jokot:admin you@example.com` sets a new password |
| Error 500 | `storage/logs/laravel.log`; usually permissions on `storage/` or a stale cache (`php artisan optimize:clear`) |
| Uploads fail | `client_max_body_size` (nginx) and `upload_max_filesize`/`post_max_size` (PHP) |
| Countries show "Onbekend" | run `php artisan geoip:update`; private/local addresses never have a country |
| Mails not sent | SMTP in Instellingen → E-mail; with `MAIL_MAILER=log` and no SMTP, mails only go to the log |
| Payment stays "pending" | Stripe webhook URL/secret; the cron job for expiring orders |
