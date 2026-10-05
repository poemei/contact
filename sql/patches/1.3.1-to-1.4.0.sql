CREATE TABLE IF NOT EXISTS contact_schema (
    id TINYINT UNSIGNED NOT NULL,
    schema_version VARCHAR(64) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO contact_schema (id, schema_version)
VALUES (1, '1.4.0')
ON DUPLICATE KEY UPDATE schema_version = VALUES(schema_version);
