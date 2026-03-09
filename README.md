# Cardy

A self-hosted **CardDAV** and **CalDAV** server with a web-based management UI, built with PHP, MySQL and Nginx.

---

## Features

- **CardDAV** — sync contacts with any standard client (iOS, Android, Thunderbird, GNOME Contacts…)
- **CalDAV** — sync calendars and events with any standard client
- **Web UI** — manage contacts and calendar events through a browser on port **80**
- **Multi-user** — each user gets their own address book and calendar; admin panel for user management
- **SabreDAV** — industry-standard PHP DAV library for full protocol compliance

---

## Requirements

- Debian 11/12 or Ubuntu 22.04/24.04
- PHP 8.1+ (installer automatically selects the latest available supported PHP-FPM package)
- MySQL 8.0+ / MariaDB 10.6+
- Nginx
- Composer

---

## How To Get Started

```bash
git clone https://github.com/TwiStarSystems/Cardy.git
cd Cardy
sudo bash install.sh
```

---

## Quick Install

```bash
sudo bash install.sh
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

After installation, visit `http://your-server` to log in.

---

## Ports

| Service          | Port | Notes                              |
|------------------|------|------------------------------------|
| Web UI + DAV     | 80   | Unified endpoint for both services |

---

## DAV Client Configuration

| Setting       | Value                                                              |
|---------------|--------------------------------------------------------------------|
| CardDAV URL   | `http://your-server/addressbooks/<username>/default/`              |
| CalDAV URL    | `http://your-server/calendars/<username>/default/`                 |
| Username      | Your Cardy username                                                |
| Password      | Your Cardy password                                                |

---

## CLI Management (`cardy-ctl`)

```bash
cardy-ctl user:list                    # list all users
cardy-ctl user:add johndoe --admin     # create an admin user
cardy-ctl user:password johndoe        # change password
cardy-ctl user:delete johndoe          # remove user + all data
cardy-ctl db:migrate                   # (re-)apply database schema
```

---

## Updating

```bash
sudo bash install.sh --update
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
│       └── cardy.conf         Unified Nginx vhost for Web UI + DAV (port 80)
├── src/
│   ├── Config.php
│   ├── Database.php
│   ├── Backend/Auth.php       SabreDAV HTTP Basic Auth backend
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
