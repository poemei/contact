-- Contact MVC 1.3.0 -> 1.3.1
-- Sender identity belongs to ChAoS MVC app/lib/mailer.php and app/data/mailer.json.
-- Contact retains only module-owned acknowledgement content.
ALTER TABLE contact_config
    DROP COLUMN sender_name,
    DROP COLUMN sender_email;
