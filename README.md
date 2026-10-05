# Contact MVC

Contact MVC is a ChAoS MVC user module for receiving, storing, routing, and administratively managing website contact inquiries.

## Department Routing

The public Contact form loads its department dropdown from the module-owned `contact_departments` table. Each department has its own administrator-configured recipient email address.

When a visitor submits an inquiry, Contact routes the internal notification to the email address configured for the selected department. The visitor's address is used as Reply-To rather than being spoofed as the sender.

Administrators can add, edit, activate/deactivate, order, and remove departments from `/admin/contact`.

## Mail Delivery

Contact uses the ChAoS MVC installation mail contract directly:

```php
$mail = (new mailer())->create();
```

`mailer::create()` owns SMTP transport configuration and the installation-wide From identity. Contact does not store or override SMTP host, credentials, encryption, sender email, or sender name.

Contact owns only the mail content and destinations that belong to the Contact module:

- Department recipient email addresses
- End-user acknowledgement subject
- End-user acknowledgement message
- Administrator reply text

After a successful public submission, Contact creates separate mailer instances and sends:

1. An internal notification to the selected department. The visitor is set as Reply-To.
2. An acknowledgement to the end user. The selected department is set as Reply-To.

The exact acknowledgement text saved in Contact Admin is sent to the end user as plain text.

The Admin inquiry reply textbox also creates a fresh `mailer::create()` instance and sends the exact saved reply text directly to the inquiry's end user.

## Database Lifecycle

Contact follows the ChAoS MVC module lifecycle:

- Missing schema: public Contact is unavailable and Admin presents **Install SQL**.
- Update required: normal database-dependent Admin operations are withheld and Admin presents **Update SQL**.
- Current schema: normal Contact, department, acknowledgement configuration, and inquiry operations are available.
- **Delete Data** removes stored inquiries while preserving schema, department routing, acknowledgement configuration, and module files.
- **Nuke** remains ChAoS MVC Core-owned complete removal through `/admin/uninstall`.

All module-owned state changes use POST and ChAoS MVC CSRF protection.

**Delete Data belongs to the module. Nuke belongs to Core.**

**Secure the Core. Grow outwards.**
