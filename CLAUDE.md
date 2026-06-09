# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Cardy is a self-hosted **CardDAV + CalDAV server with a web management UI**, written in PHP 8.1+ on top of **SabreDAV** (`sabre/dav`), backed by MySQL/MariaDB, served by Nginx + PHP-FPM. There is no build step and no test suite — code runs directly from source.

## Commands

```bash
composer install                        # install PHP deps (sabre/dav, sabre/vobject)

./cardy-ctl user:list                   # list users
./cardy-ctl user:add <name> --role=admin|user
./cardy-ctl user:password <name>        # change password
./cardy-ctl user:delete <name>          # delete user + all their data
./cardy-ctl db:migrate                  # (re-)apply sql/schema.sql

sudo bash install.sh --fresh-install    # wipe + install (deletes DB, configs, data)
sudo bash install.sh --update           # update files/schema/nginx, preserve data
sudo bash install.sh --uninstall
```

There is no linter, formatter, or test runner configured. `cardy-ctl` and both entry points require `config/config.php` to exist (copy from `config/config.php.example`) and refuse to run otherwise.

## Architecture

**Two entry points, one Nginx vhost on port 80** (`config/nginx/cardy.conf` routes DAV paths vs. everything else):

- `public/dav/index.php` — builds a `Sabre\DAV\Server` over the standard SabreDAV PDO backends (`PrincipalBackend\PDO`, `CardDAV\Backend\PDO`, `CalDAV\Backend\PDO`) plus a stack of Sabre plugins. Serves `/principals/`, `/addressbooks/<user>`, `/calendars/<user>`.
- `public/webui/index.php` — a hand-rolled front-controller router. Routes are a `GET`/`POST` => `[Controller::class, 'method']` map; `{id}` patterns become named regex captures passed as `$params` to the action. Add new pages by editing the `$routes` array here.

**Autoloading**: PSR-4 `Cardy\` => `src/`.

**Two auth systems share one `users` table** (`password_hash` column, bcrypt/argon2):
- DAV: HTTP Basic via `src/Backend/Auth.php` (extends Sabre `AbstractBasic`).
- Web UI: PHP session (`cardy_session`) + CSRF tokens, enforced by the `Controller` base class.

**Controllers** (`src/WebUI/Controllers/`) all extend `src/WebUI/Controller.php`, which provides `render()`, `redirect()`, `json()`, `abort()`, `requireAuth()`, `requireAdmin()`, `csrfToken()`/`verifyCsrf()`, `flash()`/`getFlash()`, and `e()` (HTML escape). Templates are plain PHP in `templates/`; `render('foo/bar', $data)` extracts `$data` and includes `templates/foo/bar.php`. `$_ctrl` is available inside templates for helpers like `$_ctrl->e()`.

**Models wrap SabreDAV's own tables** rather than defining new ones. `Contact` operates on `cards` + `addressbooks`, `CalendarEvent` on `calendarobjects` + `calendars`. They use `sabre/vobject` to read/write raw vCard/iCal. **Key invariant**: Web UI edits must update only managed fields and *preserve unknown/custom vCard/iCal properties* already on the record — the DB stores the full raw payload (`cards.carddata`), so don't regenerate records from scratch.

**Runtime self-migration**: models lazily add their own columns/tables/indexes on first use via `SHOW COLUMNS`/`ALTER TABLE` guards (e.g. `Contact::ensureLocalIdColumn()`, group/history table checks). This means **`sql/schema.sql` is not the complete source of truth** — some columns (like `cards.local_id`) only exist after the relevant model runs. When adding schema, follow the existing pattern: add to `schema.sql` *and* add an idempotent runtime ensure-check if models depend on it before a migration runs.

**Per-user reusable contact IDs**: `cards.local_id` is a per-addressbook numeric ID; `Contact::nextFreeLocalId()` reuses the lowest free integer after deletions. Contact URLs use this local ID, not the SabreDAV row ID.

**Config + DB access**: `Cardy\Config::load($path)` then `Config::get('app.foo', $default)` (dot notation). `Cardy\Database::getInstance()` is a PDO singleton (utf8mb4, exceptions, real prepares); `setInstance()` lets the DAV entry point share one PDO across Sabre backends.

**Reverse proxy / TLS**: TLS terminates on a front Nginx; backend Cardy stays HTTP. `src/Http/TrustedProxy.php` rewrites `X-Forwarded-*` only for IPs in `app.trusted_proxies`, and is applied at the top of both entry points. `app.webui_url`/`app.dav_url` must be set to the public HTTPS URL behind a proxy.

**Styling**: all CSS lives in `public/webui/assets/css/style.css` using the TwiStar color scheme via CSS custom properties (`--color-twistar-purple`, `--gradient-primary`, …). `templates/layout.php` is the shared page chrome.
