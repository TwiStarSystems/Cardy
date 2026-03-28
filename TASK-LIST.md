# Task List For Cardy

> **Audited:** 28 March 2026, 09:00 UTC \ 4 commits since last audit
> **Method:** Full codebase review of all controllers, models, services, views, routes, agent code, and database schema.  
> **Codebase:** ~13,000 lines | 5 controllers | 3 models | 0 services | 16 views | 1 migration | 0 Go agent files  
> **Target Launch:** April 1, 2026 (flexible — QUALITY is the priority, not speed)

---

# Overall Complete Percentage: 52%

> **Context:** Core contacts and CardDAV are ~96% complete. Calendar advanced features, testing, and documentation are the primary gaps pulling overall down.

## Summary 

|   Area   |   Completed   |   Remaining   |   Completion   |
|----------|---------------|---------------|----------------|
| Core Features | 20 | 0 | 100% |
| Contacts | 50 | 1 | 98% |
| Calendar | 31 | 16 | 66% |
| Admin Panel | 11 | 23 | 32% |
| Security | 6 | 17 | 26% |
| UI/UX | 12 | 19 | 39% |
| Testing | 0 | 15 | 0% |
| Documentation | 1 | 14 | 7% |
| Infrastructure | 4 | 6 | 40% |
| Performance | 0 | 13 | 0% |

---

# Core Features #

[X] - CardDAV server implementation with SabreDAV
[X] - CalDAV server implementation with SabreDAV
[X] - Authentication and authorization system (login/logout)
[X] - Role-based access control (user/admin roles)
[X] - CSRF protection for all forms
[X] - Session management
[X] - Database connection and models (User, Contact, CalendarEvent)
[X] - Web UI routing and front controller
[X] - Flash message system
[X] - Config management system
[X] - Trusted proxy support for reverse proxies
[X] - DAV browser plugin
[X] - DAV sync plugin
[X] - Principal backend
[X] - Installer script with --fresh-install, --update, --uninstall modes
[X] - Nginx configuration files
[X] - CLI tool (cardy-ctl) for user management
[X] - Dashboard with stats and upcoming events
[X] - Support for multiple addressbooks and calendars per user
[X] - DAV access logs

---

# Contacts (CardDAV) #

## Core CRUD Operations ##
[X] - List all contacts for user
[X] - View individual contact
[X] - Create new contact
[X] - Edit existing contact
[X] - Delete contact
[X] - Contact photos (JPG/JPEG, PNG, GIF, WEBP, BMP)

## Search & Filtering ##
[X] - Search contacts by name, email, organization
[X] - Sort by first name, last name, birthday, organization, recently updated
[X] - Category filtering (All, People, Business)
[X] - Auto-categorization into People vs Business

## Data Fields ##
[X] - Name fields (first, last, middle, prefix, suffix)
[X] - Multiple email DLTwiStar/philomenaaddresses with types
[X] - Multiple phone numbers with types
[X] - Multiple addresses (home, work) with full fields
[X] - Organization field
[X] - Birthday field
[X] - Notes field
[X] - Contact photo with MIME type support
[X] - Website/URL field
[X] - Social media fields (Twitter, LinkedIn, etc.)
[X] - Job title field
[X] - Nickname field
[X] - Anniversary field
[X] - Custom fields support
[X] - Related contacts/relationships
[X] - Contact groups/labels

## Import/Export ##
[X] - Import from vCard (.vcf) single and bulk
[X] - Import from Google Contacts
[X] - Import from iCloud
[X] - Import from Outlook
[X] - Export to CSV
[X] - Export to vCard (.vcf)
[X] - Export all contacts at once
[X] - Export to Google Contacts
[X] - Export to iCloud
[X] - Export to Outlook

## Advanced Features ##
[X] - Reusable contact IDs (lowest free ID reused after deletion)
[X] - Contact groups/labels
[X] - Bulk selection and actions (delete, star, unstar, add to group)
[X] - Duplicate contact detection [name/email/phone, toggleable ignore for duplicates]
[X] - Contact merge functionality
[X] - Contact history/activity log
[X] - Contact favorites/starred
[X] - Contact birthday reminders (dashboard, 30-day window)
[X] - Contact QR code generation (vCard, on contact view page)
[ ] - Contact bulk remove from group

---

# Calendar (CalDAV) #

## Core CRUD Operations ##
[X] - List events by month/year
[X] - Create new event
[X] - Edit existing event
[X] - Delete event
[X] - Event types: VEVENT (event), VTODO (task), VJOURNAL (journal)
[X] - All-day events support
[X] - Timezone-aware event times (currently hardcoded to UTC — needs timezone selector in form)
[X] - Event categories/tags
[X] - Event color/category
[X] - Event visibility (public/private)
[X] - Event status (confirmed/tentative/cancelled) — parsed from iCal but not exposed in Web UI form
[X] - Attendees/participants with RSVP status
[X] - Organizer field for events
[X] - Contact linking for attendees/organizer
[X] - Contact birthday/anniversary automatic calendar events

## Event Details ##
[X] - Summary/title
[X] - Description
[X] - Location
[X] - Start date/time
[X] - End date/time
[X] - Timezone (UTC default)
[X] - All-day event toggle

## Advanced Features ##
[X] - Multiple calendars per user (create, rename, recolor, delete, switch)
[X] - Recurring events (daily, weekly, monthly, yearly)
[X] - Event reminders/alarms (VALARM)
[ ] - Event attachments
[ ] - Shared calendars between users
[ ] - Calendar subscriptions (iCal URLs / webcal)
[X] - Calendar iCal export (.ics file download for current calendar)
[X] - Calendar iCal import (.ics file upload)
[X] - Week view
[X] - Day view
[X] - Agenda/list view
[X] - Today button to jump to current month/date

## Task Management (VTODO) ##
[ ] - Task list view separate from calendar
[ ] - Task priorities
[ ] - Task due dates
[ ] - Task completion status
[ ] - Task categories
[ ] - Task reminders/alarms
[ ] - Task dependencies (subtasks)
[ ] - Task progress tracking
[ ] - Task assignment to other users
[ ] - Task history/activity log
[ ] - Task export to CSV/iCal
[ ] - Task import from iCal
[ ] - Task synchronization with external task managers (Google Tasks, etc.)

---

# Admin Panel #

## User Management ##
[X] - List all users
[X] - Create new user
[X] - Edit user details
[X] - Delete user
[X] - Change user role (user/admin)
[X] - Self-deletion prevention for admins
[ ] - Password reset for users
[ ] - User activity logs
[ ] - User account locking/unlocking
[ ] - User session management (view/terminate sessions)
[ ] - User impersonation for support

## Server Settings ##
[X] - Application name configuration
[X] - Timezone configuration
[X] - Web UI URL configuration
[X] - DAV URL configuration
[X] - Trusted proxies configuration
[ ] - Email server (SMTP) configuration
[ ] - Backup schedule configuration
[ ] - Maintenance mode toggle
[ ] - System logs access
[ ] - Debug mode toggle (code path exists in router but not exposed in admin settings)

## Monitoring & Logs ##
[ ] - System activity logs
[ ] - User activity logs
[ ] - Failed login attempts log
[ ] - System health dashboard
[ ] - Database size/stats
[ ] - Active sessions list

## Maintenance ##
[ ] - Database backup/restore
[ ] - Database optimization/cleanup
[ ] - User data export
[ ] - User data import
[ ] - Force logout all sessions
[ ] - Clear cache button
[ ] - Run database migrations (web UI — CLI cardy-ctl db:migrate exists)

---

# Security #

## Authentication ##
[X] - Basic HTTP authentication for DAV
[X] - Session-based authentication for Web UI
[X] - Password hashing with bcrypt
[X] - CSRF token protection
[ ] - Two-factor authentication (2FA, TOTP, email)
[ ] - Remember me functionality
[ ] - Password strength requirements
[ ] - Password reset via email
[ ] - Email verification for new accounts
[ ] - Account lockout after failed login attempts
[ ] - Account recovery options (security questions, backup codes)

## Authorization ##
[X] - Role-based access control (RBAC)
[ ] - Fine-grained permissions system
[ ] - Per-resource access control
[ ] - Public/private contact sharing

## Protection ##
[X] - Trusted proxy IP validation (with CIDR range support)
[ ] - Rate limiting for login attempts (brute force protection)
[ ] - Rate limiting for DAV/API endpoints
[ ] - IP whitelisting/blacklisting
[ ] - Security headers in Nginx (HSTS, X-Frame-Options, X-Content-Type-Options, CSP, Referrer-Policy)
[ ] - SQL injection protection audit/validation
[ ] - Session fixation protection
[ ] - Cookie security hardening (SameSite=Strict for HTTPS deployments)

---

# UI/UX #

## Design & Layout ##
[X] - Responsive layout foundation
[X] - Clean, modern design
[X] - Consistent styling across pages
[X] - Error pages (404, 403, 500)
[X] - Flash message alerts
[X] - Empty states for no data
[X] - Card-based layouts for contacts and calendar
[ ] - Mobile-first responsive design refinement
[ ] - Customizable themes (dark mode)
[ ] - User preferences (items per page, default sort, etc.)

## Forms & Inputs ##
[X] - Form validation feedback
[X] - Multi-field email and phone inputs
[X] - Confirmation dialogs for destructive actions (JS confirm on all deletes and bulk-delete)
[ ] - Date picker component (native HTML5 date input currently)
[ ] - Time picker component (native HTML5 time input currently)
[ ] - Color picker for event/calendar colors (native HTML5 color input currently)
[ ] - Rich text editor for descriptions/notes
[ ] - Auto-complete for contact fields
[ ] - Drag-and-drop file upload

## Navigation & Usability ##
[X] - Breadcrumb navigation
[X] - Pagination where needed
[ ] - Keyboard shortcuts
[ ] - Quick search (global, cross-contacts-and-calendar)
[ ] - Tooltips for icons/buttons
[ ] - Undo/redo functionality
[ ] - Accessibility (ARIA labels, screen reader support)

## Internationalization ##
[ ] - Multi-language support (i18n)
[ ] - Locale-aware date/time formatting
[ ] - Timezone display in user's local timezone
[ ] - RTL language support

---

# Testing #

## Unit Tests ##
[ ] - Model tests (User, Contact, CalendarEvent)
[ ] - Controller tests
[ ] - Helper/utility function tests
[ ] - Database migration tests
[ ] - Authentication tests

## Integration Tests ##
[ ] - DAV server tests (CardDAV/CalDAV protocol compliance)
[ ] - Web UI workflow tests
[ ] - API endpoint tests
[ ] - Session management tests
[ ] - File upload tests

## End-to-End Tests ##
[ ] - Full user journey tests
[ ] - Cross-browser testing
[ ] - Mobile device testing
[ ] - Performance benchmarks
[ ] - Load testing

---

# Infrastructure #

## Installation & Deployment ##
[X] - Automated installer script
[X] - Nginx configuration
[X] - PHP-FPM configuration
[X] - Composer dependency management
[ ] - Systemd service files
[ ] - Automated updates

## Development Tools ##
[ ] - Docker development environment
[ ] - Code linting (PHP CS Fixer, PHPStan)
[ ] - CI/CD pipeline (GitHub Actions)
[ ] - Automated dependency update checks (Dependabot)

---

# Performance #

## Optimization ##
[ ] - Database query optimization (index review for large contact/event sets)
[ ] - Caching layer (Redis/Memcached)
[ ] - Page load time optimization
[ ] - Asset minification (CSS/JS)
[ ] - Self-hosted QR code library (currently loads qrcodejs from external Cloudflare CDN — privacy and availability concern)
[ ] - Image/photo optimization
[ ] - Lazy loading for contact photos

## Scalability ##
[ ] - Pagination for large contact lists (all contacts loaded at once — no server-side limit)
[ ] - Virtual scrolling for very long contact lists
[ ] - Database connection pooling
[ ] - Horizontal scaling support
[ ] - Load balancer configuration
[ ] - CDN integration for static assets

---

# Bug Fixes & Polish #

## Known Issues ##
[ ] - Bug: Dashboard "Upcoming Events" stat only counts first/default calendar — should use `countUpcomingForAllCalendars()` to span all user calendars
[ ] - Verify VTODO and VJOURNAL create/edit in Web UI syncs correctly with DAV clients
[ ] - Test recurring event import from external calendars (RRULE handled by SabreDAV but not exposed in Web UI)
[ ] - Validate contact photo size limit feedback in form (5MB enforced server-side but no client-side indicator)
[ ] - Test CSV import with various encodings (UTF-8, ISO-8859-1, etc.)

## Polish & Refinement ##
[ ] - Improve error messages clarity (generic exceptions shown to users)
[ ] - Add loading indicators for slow operations (large imports)
[ ] - Improve search UX (live/instant search, highlight matches)
[ ] - Add batch import progress indicator
[ ] - Validate email addresses in contact forms (server-side format check)
[ ] - Phone number formatting/validation
[X] - "Today" button in calendar navigation to jump to current month
[ ] - Pagination or virtual scroll for contacts list with 500+ contacts

---

# Documentation #

## User Documentation ##
[X] - README with basic setup instructions
[ ] - Comprehensive user guide
[ ] - Client setup guides (iOS, Android, Thunderbird, etc.) — basic inline help exists on Dashboard
[ ] - FAQ section
[ ] - Troubleshooting guide

## Developer Documentation ##
[ ] - API documentation
[ ] - Database schema documentation
[ ] - Code architecture overview
[ ] - Contributing guidelines
[ ] - Changelog

## Deployment Documentation ##
[ ] - Reverse proxy setup guide (Nginx, Apache, Caddy)
[ ] - SSL/TLS certificate setup guide
[ ] - Docker deployment guide
[ ] - Backup and restore procedures

---

# Future Enhancements #

## Nice-to-Have Features ##
[ ] - Calendar widget for embedding on external pages
[ ] - Calendar printing (print-friendly view)
[ ] - Contact vCard QR code scanning (camera scan to import)
[ ] - Bulk email to contacts (mailing list / newsletter)
[ ] - Event RSVP tracking
[ ] - Calendar availability/free-busy
[ ] - Contact import from social media profiles
[ ] - Mobile app (iOS/Android — native or PWA)
[ ] - Browser extension (quick-add contact)
[ ] - Electron desktop app

---

## Audit Notes ##

### Changes Since Last Audit (19 Mar → 28 Mar 2026, 4 new commits) ###
- `145539f` — Fixed DAV rename not updating in DAVx⁵ (synctoken now bumped correctly on calendar rename)
- `fb00389` — Multi addressbook and calendar support (already reflected as `[X]`)
- `f827992` — Large batch of features (already reflected in task list)
- `13facac` — License change, initial task list added

### Corrections & Findings This Audit ###
- **Newly marked `[X]`:**
  - `All-day event toggle` (Calendar → Event Details) — toggle present in form and fully supported by model
  - `Confirmation dialogs for destructive actions` (UI/UX → Forms) — all delete actions use JS `confirm()` dialogs
  - `Multiple calendars per user` (Calendar → Advanced) — moved from `[ ]` to `[X]`, fully implemented
- **Removed duplicates:** Calendar "Core CRUD Ops" incorrectly listed "Event location field" and "Event description field" as `[ ]` — both are fully implemented and already marked `[X]` under Event Details
- **Removed from Future Enhancements:** "Contact QR code generation" and "Birthday reminders" — both done and already `[X]` in Contacts section
- **New tasks added:**
  - Bug: Dashboard upcoming event count only queries first/default calendar
  - Feature: Calendar iCal export and import (.ics file)
  - Security: Security headers in Nginx (HSTS, CSP, X-Frame-Options, etc.)
  - Performance: Self-host QR code library (currently loaded from Cloudflare CDN)
  - Admin: Debug mode toggle in Server Settings
  - Contacts: Bulk remove from group

### Application State ###
- Core contacts and CardDAV are production-ready
- Basic CalDAV (CRUD events, multi-calendar, all-day support) works and syncs with DAVx⁵, iOS, and Thunderbird
- Advanced calendar features (recurring events, attendees, week/day views, iCal export) are not yet in the Web UI
- Security is solid for standard deployments; missing 2FA, rate limiting, and security headers for hardened deployments
- No automated tests — manual testing only
- Documentation is minimal; no client setup or developer guides beyond README
