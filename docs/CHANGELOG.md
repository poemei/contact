# Contact MVC Changelog

## 1.3.3 - 2026-10-05

### Added

- Added a deterministic honeypot field to the public Contact form.
- Honeypot submissions are silently discarded before inquiry storage or mail delivery and receive the normal success redirect.
- Added server-side rejection of URLs in the public name, subject, and message fields.
- Added a 20-character minimum for public inquiry messages.
- Added basic message-content validation requiring at least two consecutive ASCII letters.
- Added a public notice that links are not permitted in Contact submissions.

### Behavior

- Anti-spam validation occurs before `create_inquiry()`, preventing rejected submissions from entering Contact storage or triggering department notifications or end-user acknowledgement mail.
- Existing Contact Admin, department routing, acknowledgement, inquiry management, database lifecycle, and Core-owned Nuke behavior are unchanged.
- This is a code-only patch release; no database migration is required.

## 1.3.2 - 2026-09-05

### Added

- Added one-request status reporting to **Admin → Contact** for Contact-owned Admin actions.
- Successful Admin replies now report **Message Sent**.
- Failed Admin reply delivery now reports **Message Send Failed** with the available mailer/PHPMailer error text for the authenticated administrator.
- Validation, lifecycle, department, configuration, inquiry, and database-operation errors are surfaced in the Contact Admin status area.
- Added success messages for SQL installation/update, acknowledgement configuration, department CRUD, inquiry deletion, inquiry-only saves, and Delete Data.

### Behavior

- Status messages use session-backed flash state so they survive POST/redirect/GET and display only once.
- Status text is escaped before rendering.
- Mail delivery continues to use ChAoS MVC `mailer::create()` and installation-level SMTP/From configuration.
- This is a code-only patch release; no database migration is required.
- **Nuke** remains Core-owned and continues to post to `/admin/uninstall`.

## 1.3.1 - 2026-09-05

### Corrected

- Contact now uses the ChAoS MVC `mailer::create()` contract for every outbound message.
- Removed Contact-owned sender name and sender email configuration; SMTP transport and From identity remain installation-level responsibilities of `app/lib/mailer.php` and `app/data/mailer.json`.
- Added an exact `1.3.0-to-1.3.1.sql` migration that removes the obsolete sender identity columns from `contact_config`.
- Updated fresh-install schema so `contact_config` stores only Contact-owned end-user acknowledgement content.
- Public Contact availability now verifies that the installation mailer can be created before accepting submissions.

### Mail Behavior

- Internal department notification uses a fresh `mailer::create()` instance, sends to the selected department address, and sets the visitor as Reply-To.
- End-user acknowledgement uses a separate `mailer::create()` instance and sends the exact Admin-configured acknowledgement text to the visitor.
- Admin replies use a fresh `mailer::create()` instance and send the exact saved reply text to the inquiry's end user.
- Department recipient addresses remain module-owned routing data.

### Lifecycle

- Existing 1.3.0 installations present **Update SQL** until the obsolete sender columns are removed.
- Existing 1.1.0 and 1.2.0 installations can advance through the packaged migration chain to 1.3.1.
- **Delete Data** continues to remove inquiries only.
- **Nuke** remains visible and delegates complete removal to ChAoS MVC Core `/admin/uninstall`.

## 1.3.0 - 2026-09-05

### Added

- Added a module-owned `contact_config` table for installation-specific mail identity and end-user acknowledgement content.
- Added Admin configuration for sender name, sender email, confirmation subject, and confirmation message.
- Added a per-department `email_address` used as the internal delivery destination for public inquiries.
- Added an exact `1.2.0-to-1.3.0.sql` database migration.
- Added end-user confirmation email delivery after a successful public Contact submission.
- Added department-address Reply-To handling for end-user mail.

### Changed

- Public inquiries are routed to the administrator-configured email address of the selected department rather than a hardcoded support mailbox.
- Internal notifications use the configured installation sender identity and the end user's address as Reply-To.
- The Admin inquiry reply textbox now sends the saved reply text itself to the inquiry's end user when **Save & Send** is selected.
- Migrated departments without recipient addresses are disabled until an administrator configures a valid department email address.
- Public Contact remains unavailable until a valid sender identity and at least one active routable department are configured.
- **Delete Data** now removes operational inquiry records while preserving department routing and Contact mail configuration.
- The Core-owned **Nuke** control remains present and posts to `/admin/uninstall` for complete module removal.

### Lifecycle

- Fresh installs receive the complete 1.3.0 schema.
- Existing 1.2.0 installs receive an explicit **Update SQL** action before normal database-dependent operations resume.
- Existing 1.1.0 installations can advance through the packaged 1.1.0 → 1.2.0 and 1.2.0 → 1.3.0 migration path.
- No database installation or migration occurs silently on a normal public or Admin page request.

## 1.2.0 - 2026-09-05

### Added

- Added the module-owned `contact_departments` table.
- Added Admin department creation, editing, activation/deactivation, ordering, and removal.
- Added fresh-install `sql/schema.sql` and exact `1.1.0-to-1.2.0.sql` migration.
- Added explicit module database lifecycle states: `missing`, `update`, `current`, and `error`.
- Added Admin **Install SQL**, **Update SQL**, **Delete Data**, and Core-owned **Nuke** controls.
- Added CSRF protection to public Contact submission and every module-owned Admin state change.

### Changed

- Public department selection is now populated from active administrator-managed departments instead of hardcoded HTML options.
- Admin database-dependent queries are gated until the module schema is current.
- Inquiry deletion is now an authenticated POST action rather than a GET mutation.
- Contact controller/model code was reformatted to PSR-12 style where affected.

### Preserved

- Existing inquiry capture, access-level visibility, technician replies, and mail notifications remain part of the module behavior.
- The three former hardcoded department choices are seeded during fresh installation and upgrade so the existing public UX is preserved initially.

## 1.1.0

- Previous packaged release baseline.
