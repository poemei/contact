-- Contact MVC 1.1.0 -> 1.2.0
-- Adds administrator-managed departments used by the public Contact dropdown.
CREATE TABLE IF NOT EXISTS contact_departments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(64) NOT NULL,
    name VARCHAR(255) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_contact_departments_slug (slug),
    KEY idx_contact_departments_active_sort (active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO contact_departments (slug, name, active, sort_order) VALUES
    ('support', 'General Support', 1, 10),
    ('devteam', 'Development Team', 1, 20),
    ('billing', 'Billing/Marketplace', 1, 30);
