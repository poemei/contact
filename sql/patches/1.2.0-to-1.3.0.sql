-- Contact MVC 1.2.0 -> 1.3.0
-- Adds per-department recipient addresses and installation-wide mail configuration.
ALTER TABLE contact_departments
    ADD COLUMN email_address VARCHAR(255) NULL AFTER name;

UPDATE contact_departments
SET active = 0
WHERE email_address IS NULL OR email_address = '';

CREATE TABLE IF NOT EXISTS contact_config (
    id TINYINT UNSIGNED NOT NULL,
    sender_name VARCHAR(255) NOT NULL DEFAULT '',
    sender_email VARCHAR(255) NOT NULL DEFAULT '',
    confirmation_subject VARCHAR(255) NOT NULL DEFAULT 'We received your message',
    confirmation_message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO contact_config (
    id,
    sender_name,
    sender_email,
    confirmation_subject,
    confirmation_message
) VALUES (
    1,
    '',
    '',
    'We received your message',
    'Thank you for contacting us. Your message has been received and someone will respond as soon as possible.'
);
