-- Contact MVC 1.3.1 fresh-install schema.
CREATE TABLE IF NOT EXISTS contacts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    department VARCHAR(64) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    min_level TINYINT UNSIGNED NOT NULL DEFAULT 1,
    reply_content TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contacts_min_level (min_level),
    KEY idx_contacts_department (department),
    KEY idx_contacts_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_departments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(64) NOT NULL,
    name VARCHAR(255) NOT NULL,
    email_address VARCHAR(255) NULL,
    active TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_contact_departments_slug (slug),
    KEY idx_contact_departments_active_sort (active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_config (
    id TINYINT UNSIGNED NOT NULL,
    confirmation_subject VARCHAR(255) NOT NULL DEFAULT 'We received your message',
    confirmation_message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO contact_config (
    id,
    confirmation_subject,
    confirmation_message
) VALUES (
    1,
    'We received your message',
    'Thank you for contacting us. Your message has been received and someone will respond as soon as possible.'
);

INSERT IGNORE INTO contact_departments (slug, name, email_address, active, sort_order) VALUES
    ('support', 'General Support', NULL, 0, 10),
    ('devteam', 'Development Team', NULL, 0, 20),
    ('billing', 'Billing/Marketplace', NULL, 0, 30);
