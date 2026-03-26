# Task List For Cardy

> **Audited:** 19 March 2026, 12:00 UTC \ 17 commits since last audit  
> **Method:** Full codebase review of all controllers, models, services, views, routes, agent code, and database schema.  
> **Codebase:** 6,000 lines | 5 controllers | 3 models | 0 services | 9 views | 1 migrations | 0 Go agent files  
> **Target Launch:** April 1, 2026 (flexible — QUALITY is the priority, not speed)

---

# Overall Complete Percentage: 81%

## Summary 

|   Area   |   Completed   |   Remaining   |   Completion   |
|----------|---------------|---------------|----------------|
| Core Features | 19 | 3 | 86% |
| Contacts | 33 | 2 | 94% |
| Calendar | 6 | 9 | 40% |
| Admin Panel | 5 | 6 | 45% |
| Security | 4 | 7 | 36% |
| UI/UX | 7 | 8 | 47% |
| Testing | 0 | 5 | 0% |
| Documentation | 1 | 7 | 13% |
| Infrastructure | 4 | 4 | 50% |
| Performance | 0 | 4 | 0% |

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
[ ] - DAV access logs

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
[ ] - Related contacts/relationships
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
[X] - Bulk selection and actions
[X] - Duplicate contact detection [name/email/phone, toggleable ignore for duplicates]
[X] - Contact merge functionality
[X] - Contact history/activity log
[X] - Contact favorites/starred
[X] - Contact birthday reminders
[X] - Contact QR code generation (vCard)
[ ] - Contact vCard QR code scanning

---

# Calendar (CalDAV) #

## Core CRUD Operations ##
[X] - List events by month/year
[X] - Create new event
[X] - Edit existing event
[X] - Delete event
[X] - Event types: VEVENT (event), VTODO (task), VJOURNAL (journal)
[X] - All-day events support
[ ] - Timezone-aware event times
[ ] - Event location field
[ ] - Event description field
[ ] - Event categories/tags
[ ] - Event color/category
[ ] - Event visibility (public/private)
[ ] - Event status (confirmed/tentative/cancelled)
[ ] - Attendees/participants with RSVP status
[ ] - Organizer field for events
[ ] - Contact linking for attendees/organizer
[ ] - Contact birthday/anniversary automatic calendar events

## Event Details ##
[X] - Summary/title
[X] - Description
[X] - Location
[X] - Start date/time
[X] - End date/time
[X] - Timezone (UTC default)
[ ] - All-day event toggle
[ ] - Event location field
[ ] - Event description field
[ ] - Event categories/tags
[ ] - Event color/category
[ ] - Event visibility (public/private)
[ ] - Event status (confirmed/tentative/cancelled)
[ ] - Attendees/participants
[ ] - Organizer field

## Advanced Features ##
[ ] - Recurring events (daily, weekly, monthly, yearly)
[ ] - Event reminders/alarms
[ ] - Event attachments
[ ] - Event categories/tags
[ ] - Multiple calendars per user
[ ] - Shared calendars between users
[ ] - Calendar subscriptions (iCal URLs)
[ ] - Calendar import/export (iCal format)
[ ] - Week view
[ ] - Day view
[ ] - Agenda/list view
[ ] - Today button to jump to current date

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
[ ] - Task export to CSV/vCard
[ ] - Task import from CSV/vCard
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
[ ] - Run database migrations

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
[X] - Trusted proxy IP validation
[ ] - Rate limiting for login attempts
[ ] - Rate limiting for API endpoints
[ ] - Brute force protection
[ ] - IP whitelisting/blacklisting
[ ] - Security headers (CSP, X-Frame-Options, etc.)
[ ] - SQL injection protection validation

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
[ ] - Customizable themes
[ ] - User preferences (items per page, default sort, etc.)

## Forms & Inputs ##
[X] - Form validation feedback
[X] - Multi-field email and phone inputs
[ ] - Date picker component
[ ] - Time picker component
[ ] - Color picker for event colors
[ ] - Rich text editor for descriptions/notes
[ ] - Auto-complete for contact fields
[ ] - Drag-and-drop file upload

## Navigation & Usability ##
[X] - Breadcrumb navigation
[X] - Pagination where needed
[ ] - Keyboard shortcuts
[ ] - Quick search (global)
[ ] - Tooltips for icons/buttons
[ ] - Confirmation dialogs for destructive actions
[ ] - Undo/redo functionality
[ ] - Accessibility (ARIA labels, screen reader support)

## Internationalization ##
[ ] - Multi-language support (i18n)
[ ] - Locale-aware date/time formatting
[ ] - Timezone display in user's local timezone
[ ] - Currency formatting (if needed)
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

# Documentation #

## User Documentation ##
[X] - README with basic setup instructions
[ ] - Comprehensive user guide
[ ] - Client setup guides (iOS, Android, Thunderbird, etc.)
[ ] - FAQ section
[ ] - Troubleshooting guide
[ ] - Video tutorials

## Developer Documentation ##
[ ] - API documentation
[ ] - Database schema documentation
[ ] - Code architecture overview
[ ] - Contributing guidelines
[ ] - Code style guide
[ ] - Development environment setup
[ ] - Changelog

## Deployment Documentation ##
[ ] - Reverse proxy setup guide (Nginx, Apache, Caddy)
[ ] - SSL/TLS certificate setup
[ ] - Docker deployment guide
[ ] - Performance tuning guide
[ ] - Backup and restore procedures

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
[ ] - Local development setup scripts
[ ] - Code linting (PHP CS Fixer, PHPStan)
[ ] - Git hooks for pre-commit checks
[ ] - CI/CD pipeline (GitHub Actions, GitLab CI)

---

# Performance #

## Optimization ##
[ ] - Database query optimization
[ ] - Database indexing review
[ ] - Caching layer (Redis/Memcached)
[ ] - Page load time optimization
[ ] - Asset minification (CSS/JS)
[ ] - Image optimization
[ ] - Lazy loading for images
[ ] - CDN integration

## Scalability ##
[ ] - Pagination for large contact lists
[ ] - Virtual scrolling for long lists
[ ] - Database connection pooling
[ ] - Horizontal scaling support
[ ] - Load balancer configuration

---

# Bug Fixes & Polish #

## Known Issues ##
[ ] - Verify VTODO and VJOURNAL create/edit functionality in Web UI
[ ] - Test recurring event import from external calendars
[ ] - Validate contact photo size limits
[ ] - Check timezone handling edge cases
[ ] - Test CSV import with various encodings (UTF-8, ISO-8859-1, etc.)

## Polish & Refinement ##
[ ] - Improve error messages clarity
[ ] - Add loading indicators for slow operations
[ ] - Optimize contact/event list rendering
[ ] - Add confirmation for contact/event deletion
[ ] - Improve search UX (instant search, highlight matches)
[ ] - Add batch import progress indicator
[ ] - Validate email addresses in contact forms
[ ] - Phone number formatting/validation

---

# Future Enhancements #

## Nice-to-Have Features ##
[ ] - Contact QR code generation (vCard)
[ ] - Calendar widget for embedding
[ ] - Birthday reminders
[ ] - Contact birthday calendar view
[ ] - Calendar printing (print-friendly view)
[ ] - Contact vCard QR code scanning
[ ] - Bulk email to contacts (mailing list)
[ ] - Event RSVP tracking
[ ] - Calendar availability/free-busy
[ ] - Contact import from social media
[ ] - Mobile app (iOS/Android)
[ ] - Browser extension
[ ] - Electron desktop app

---

## Notes ##

- Application is in good functional state with core features working
- Main gap is lack of testing infrastructure
- UI/UX could benefit from modern date/time pickers and improved mobile responsiveness
- Documentation needs expansion for end users and client setup guides
- Performance optimization and caching not yet implemented
- Security could be enhanced with 2FA and rate limiting
- Calendar recurring events likely work via DAV clients but Web UI doesn't expose them yet
