# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Cardy is a self-hosted **CardDAV + CalDAV server with a web management UI**, written in PHP 8.1+ on top of **SabreDAV** (`sabre/dav`), backed by MySQL/MariaDB, served by Nginx + PHP-FPM. There is no build step and no test suite — code runs directly from source.

## Servers
### App Server:
host: 172.16.5.6
port: 22
username: root
password: (PASSWORD provided when prompted)

### Nginx Reverse Proxy:
host: 172.16.6.50
port: 22
username: twistar
password: Cert authentication (use the provided private key for authentication on my user account)

### NOTE: the Config file for this app is located at "/etc/nginx/live/twistar.org/panel.mc.conf". You can modify it to change the reverse proxy settings as needed.

## Bug tracking

When a bug is discovered (during a review, sanity check, or while working on something else), **file a GitHub issue for it** — one issue per distinct bug, using `gh issue create`. Do this even if you fix it in the same session, so there is a tracked record. Title the issue with the symptom, and in the body include the affected file/function, what goes wrong, and (if known) the fix. The repo is `TwiStarSystems/Cardy`.

## Change workflow (commit + deploy)

Whenever a change lands that **(1) adds a feature, (2) fixes a bug, or (3) removes a feature**, finish it by doing **both** of the following — a change is not "done" until it is committed *and* deployed:

1. **Commit & push to GitHub.** Commit the change with a clear message (reference the issue number for bug fixes, e.g. `Fix #6: …`) and push to `TwiStarSystems/Cardy`. For bug fixes, close the corresponding issue (`gh issue close <n>` or a `Fixes #n` line in the commit/PR).
2. **Deploy the changed files to the relevant server** (see **Servers** above), based on what changed:
   - **App code** — anything under `src/`, `public/`, `templates/`, `sql/`, `config/nginx/cardy.conf`, `cardy-ctl`, or `install.sh` → deploy to the **App Server** (`172.16.5.6`). The supported update path is `sudo bash install.sh --update` on that host (pulls latest app files + DB schema while preserving user data); for a one-off file change you may sync just the affected file(s). Restart PHP-FPM if app code changed.
   - **Reverse-proxy config** — changes to the public edge vhost → deploy to the **Nginx Reverse Proxy** (`172.16.6.50`), file `/etc/nginx/live/twistar.org/panel.mc.conf`, then `nginx -t && systemctl reload nginx`.
   - A change can touch both (e.g. a new route plus a proxy rule) — deploy to each affected server.

Only skip deployment for changes that don't affect runtime (docs, this file, comments). Confirm the deploy target before pushing files to a server, and never commit secrets (server passwords, DB credentials, the proxy private key) to the repo.

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
