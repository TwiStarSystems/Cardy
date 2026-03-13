# Cardy

A self-hosted **CardDAV** and **CalDAV** server with a web-based management UI, built with PHP, MySQL and Nginx.

---

## Features

- **CardDAV** — sync contacts with any standard client (iOS, Android, Thunderbird, GNOME Contacts…)
- **CalDAV** — sync calendars and events with any standard client
- **Web UI** — manage contacts and calendar events through a browser on port **80**
- **Multi-user** — each user gets their own address book and calendar; admin panel for user management
- **RBAC** — `user` and `admin` roles with admin-only user/server management
- **Contact Import** — import single or bulk contacts from CSV or vCard (`.vcf`)
- **Contact Photos** — upload and store contact pictures (JPG/JPEG, PNG, GIF, WEBP, BMP)
- **Contact Sorting + Categories** — sort contacts by first name, last name, birthday, organization, or last update; auto-categorize listings into People vs Business
- **Reusable Contact IDs** — each contact gets a per-user numeric ID; lowest free ID is reused after deletions
- **SabreDAV** — industry-standard PHP DAV library for full protocol compliance

---

## Requirements

- Debian 13+ (optimized) or Ubuntu 22.04/24.04
- PHP 8.1+ (installer automatically selects the latest available supported PHP-FPM package)
- MySQL 8.0+ / MariaDB 10.6+
- Nginx
- Composer

---

## How To Get Started

```bash
git clone https://github.com/TwiStarSystems/Cardy.git
cd Cardy
sudo bash install.sh --fresh-install
```

---

## Quick Install

```bash
sudo bash install.sh --fresh-install
```

Installer prompt defaults:
- Database host: `localhost`
- Database port: `3306`
- Database name: `cardy`
- Database user: `cardy`
- Database password: auto-generated if left blank
- Base URI: `http://localhost` (used for both Web UI and DAV URLs)
- Admin username: `admin`
- Admin password: `admin`

The installer will:
1. Install Nginx, PHP-FPM, MySQL and Composer
2. Create the `cardy` database and user
3. Apply the database schema
4. Copy application files and run `composer install`
5. Configure a single unified Nginx vhost
6. Create an admin user
7. Configure PHP upload limits for contact images (`upload_max_filesize`, `post_max_size`)

After installation, visit `http://your-server` to log in.

Installer modes:
- `--fresh-install`: delete existing Cardy app configs, user data, DB, and nginx configs, then install cleanly.
- `--update`: update app files, DB schema, and nginx configs while preserving user data.
- `--uninstall`: delete Cardy app configs, user data, DB, and nginx configs.

---

## Ports

| Service          | Port | Notes                              |
|------------------|------|------------------------------------|
| Web UI + DAV     | 80   | Unified endpoint for both services |

---

## Reverse Proxy (Nginx)

Cardy supports running behind an Nginx reverse proxy (including TLS termination).

1. Keep Cardy running on the backend server with [config/nginx/cardy.conf](config/nginx/cardy.conf).
2. On the front proxy, use [config/nginx/reverse-proxy.example.conf](config/nginx/reverse-proxy.example.conf) as a template.
3. In [config/config.php](config/config.php), set:
	- `app.webui_url` and `app.dav_url` to your public HTTPS URL.
	- `app.trusted_proxies` to the front proxy IP(s) or CIDR(s).

Reverse proxy checklist:
- Keep backend Cardy Nginx (`cardy.conf`) on plain HTTP.
- Terminate TLS only on the public/front Nginx.
- Forward `Host`, `Authorization`, and `X-Forwarded-*` headers from front Nginx.
- Ensure backend `app.trusted_proxies` includes only your proxy addresses.

Example `trusted_proxies` values:
- `['127.0.0.1', '::1']` for same-host reverse proxy
- `['10.0.0.10']` for a dedicated proxy host
- `['10.0.0.0/24']` for a trusted subnet

---

## DAV Client Configuration

| Setting       | Value                                                              |
|---------------|--------------------------------------------------------------------|
| CardDAV URL   | `http://your-server/addressbooks/<username>/default/`              |
| CalDAV URL    | `http://your-server/calendars/<username>/default/`                 |
| Username      | Your Cardy username                                                |
| Password      | Your Cardy password                                                |

CardDAV property support notes:
- The database stores full raw vCard payloads in `cards.carddata` (SabreDAV schema), so all CardDAV/vCard properties from clients are retained.
- Web UI edits update managed fields (name, email, phone, address, etc.) while preserving unknown/custom vCard properties already present on the contact.
- New/Edit Contact form supports dynamic add/remove for emails and phone numbers (up to 100 of each per contact).
- Contact URLs and UI use per-user contact IDs for easy differentiation between contacts with the same name.
- Contacts page supports sorting by first name, last name, birthday, organization, and recently updated.
- Contacts are auto-categorized in the listing: entries with no first/last name but with organization are shown as Business contacts; all others are shown as People contacts.
- New/Edit Contact supports photo upload (JPG/JPEG, PNG, GIF, WEBP, BMP) up to 5MB.

Contact import notes:
- In Web UI, open Contacts and click **Import**.
- Supported formats: CSV and vCard (`.vcf`), both single-contact and bulk files.
- CSV headers supported include: `first_name`, `last_name`, `fn`, `email`/`email1..3`, `phone`/`phone1..3`, `org`, `title`, `birthday`, `note`, `home_*`, `work_*`.
- Sample CSV template: `public/webui/assets/examples/contacts-import-template.csv` (also downloadable from the Import page).

---

## CLI Management (`cardy-ctl`)

```bash
cardy-ctl user:list                    # list all users
cardy-ctl user:add johndoe --role=admin # create an admin user
cardy-ctl user:add alice --role=user    # create a regular user
cardy-ctl user:password johndoe        # change password
cardy-ctl user:delete johndoe          # remove user + all data
cardy-ctl db:migrate                   # (re-)apply database schema
```

---

## Updating

```bash
sudo bash install.sh --update
```

## Uninstalling

```bash
sudo bash install.sh --uninstall
```

---

## Directory Structure

```
├── composer.json              PHP dependencies
├── install.sh                 Debian/Ubuntu installation script
├── cardy-ctl                  CLI management tool
├── sql/
│   └── schema.sql             Database schema
├── config/
│   ├── config.php.example     Example configuration (copy → config.php)
│   └── nginx/
│       ├── cardy.conf         Unified Nginx vhost for Web UI + DAV (port 80)
│       └── reverse-proxy.example.conf   Example edge reverse proxy config
├── src/
│   ├── Config.php
│   ├── Database.php
│   ├── Backend/Auth.php       SabreDAV HTTP Basic Auth backend
│   ├── Http/TrustedProxy.php  Trusted reverse-proxy header handling
│   ├── Models/                User, Contact, CalendarEvent
│   └── WebUI/                 Router, Controller, Controllers
├── public/
│   ├── dav/index.php          DAV server entry point
│   └── webui/
│       ├── index.php          Web UI entry point
│       └── assets/css/style.css   Central stylesheet (TwiStar color scheme)
└── templates/                 PHP HTML templates
```

---

## Color Scheme

All styling is controlled by `/public/webui/assets/css/style.css` using the TwiStar color scheme with CSS custom properties (`--color-twistar-purple`, `--gradient-primary`, etc.).
