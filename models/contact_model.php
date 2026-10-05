<?php

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-09-06 01:40:00 UTC] */
/* [AI:GPT-5.6 Sol | 2026-09-06 01:00:00 UTC] */

/**
 * Contact module model.
 */
class contact_model extends model
{
    private const CONTACTS_TABLE = 'contacts';
    private const DEPARTMENTS_TABLE = 'contact_departments';
    private const CONFIG_TABLE = 'contact_config';

    /**
     * Determine the module database lifecycle state.
     *
     * @return string missing|update|current|error
     */
    public function database_state(): string
    {
        if (!$this->table_exists(self::CONTACTS_TABLE)) {
            return 'missing';
        }

        if (!$this->contacts_schema_valid()) {
            return 'error';
        }

        if (!$this->table_exists(self::DEPARTMENTS_TABLE)) {
            return 'update';
        }

        if (!$this->departments_base_schema_valid()) {
            return 'error';
        }

        $hasDepartmentEmail = $this->departments_schema_valid();
        $hasConfigTable = $this->table_exists(self::CONFIG_TABLE);

        if (!$hasDepartmentEmail && !$hasConfigTable) {
            return 'update';
        }

        if ($hasDepartmentEmail !== $hasConfigTable) {
            return 'error';
        }

        if (!$this->config_base_schema_valid()) {
            return 'error';
        }

        if ($this->config_has_partial_legacy_sender_columns()) {
            return 'error';
        }

        if ($this->config_has_legacy_sender_columns()) {
            return 'update';
        }

        return 'current';
    }

    /**
     * Install the current schema for a fresh module installation.
     */
    public function install_schema(): void
    {
        if ($this->database_state() !== 'missing') {
            throw new RuntimeException(
                'Contact schema installation is not available in the current database state.'
            );
        }

        $this->execute_sql_file(__DIR__ . '/../sql/schema.sql');

        if ($this->database_state() !== 'current') {
            throw new RuntimeException(
                'Contact schema installation did not produce the expected current schema.'
            );
        }
    }

    /**
     * Apply the required packaged migration path to the current schema.
     */
    public function update_schema(): void
    {
        if ($this->database_state() !== 'update') {
            throw new RuntimeException(
                'Contact schema update is not available in the current database state.'
            );
        }

        if (!$this->table_exists(self::DEPARTMENTS_TABLE)) {
            $this->execute_sql_file(__DIR__ . '/../sql/patches/1.1.0-to-1.2.0.sql');
        }

        if (
            !$this->departments_schema_valid()
            && !$this->table_exists(self::CONFIG_TABLE)
        ) {
            $this->execute_sql_file(__DIR__ . '/../sql/patches/1.2.0-to-1.3.0.sql');
        }

        if (
            $this->table_exists(self::CONFIG_TABLE)
            && $this->config_base_schema_valid()
            && $this->config_has_legacy_sender_columns()
        ) {
            $this->execute_sql_file(__DIR__ . '/../sql/patches/1.3.0-to-1.3.1.sql');
        }

        if ($this->database_state() !== 'current') {
            throw new RuntimeException(
                'Contact schema update did not produce the expected current schema.'
            );
        }
    }

    /**
     * Remove operational Contact records while preserving schema and configuration.
     */
    public function delete_data(): void
    {
        if ($this->database_state() !== 'current') {
            throw new RuntimeException(
                'Contact data cannot be deleted until the database schema is current.'
            );
        }

        $this->query('DELETE FROM ' . self::CONTACTS_TABLE);
    }

    /**
     * Save a public contact inquiry.
     *
     * @param array<string, mixed> $data
     */
    public function create_inquiry(array $data): mixed
    {
        return $this->insert(self::CONTACTS_TABLE, $data);
    }

    /**
     * Return inquiries visible to the supplied access level.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_visible_inquiries(int $level): array
    {
        return $this->fetchAll(
            'SELECT * FROM ' . self::CONTACTS_TABLE
            . ' WHERE min_level <= :level'
            . ' ORDER BY min_level DESC, created_at ASC',
            ['level' => $level]
        );
    }

    /**
     * Fetch one inquiry by ID.
     *
     * @return array<string, mixed>|false
     */
    public function get_inquiry(int $id): array|false
    {
        return $this->fetch(
            'SELECT * FROM ' . self::CONTACTS_TABLE . ' WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    /**
     * Update an inquiry.
     *
     * @param array<string, mixed> $data
     */
    public function update_ticket(int $id, array $data): mixed
    {
        return $this->update(
            self::CONTACTS_TABLE,
            $data,
            'id = :target_id',
            ['target_id' => $id]
        );
    }

    /**
     * Delete one inquiry.
     */
    public function delete_ticket(int $id): mixed
    {
        return $this->query(
            'DELETE FROM ' . self::CONTACTS_TABLE . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    /**
     * Return active, routable departments for the public contact form.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_active_departments(): array
    {
        return $this->fetchAll(
            'SELECT id, slug, name, email_address, sort_order'
            . ' FROM ' . self::DEPARTMENTS_TABLE
            . ' WHERE active = 1 AND email_address IS NOT NULL AND email_address <> \'\''
            . ' ORDER BY sort_order ASC, name ASC'
        );
    }

    /**
     * Return all departments for Admin management.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_departments(): array
    {
        return $this->fetchAll(
            'SELECT id, slug, name, email_address, active, sort_order, created_at, updated_at'
            . ' FROM ' . self::DEPARTMENTS_TABLE
            . ' ORDER BY sort_order ASC, name ASC'
        );
    }

    /**
     * Fetch one active public department by slug.
     *
     * @return array<string, mixed>|false
     */
    public function get_active_department(string $slug): array|false
    {
        return $this->fetch(
            'SELECT id, slug, name, email_address'
            . ' FROM ' . self::DEPARTMENTS_TABLE
            . ' WHERE slug = :slug'
            . ' AND active = 1'
            . ' AND email_address IS NOT NULL'
            . ' AND email_address <> \'\''
            . ' LIMIT 1',
            ['slug' => $slug]
        );
    }

    /**
     * Create a department.
     */
    public function create_department(
        string $slug,
        string $name,
        string $emailAddress,
        bool $active,
        int $sortOrder
    ): mixed {
        return $this->insert(
            self::DEPARTMENTS_TABLE,
            [
                'slug' => $slug,
                'name' => $name,
                'email_address' => $emailAddress,
                'active' => $active ? 1 : 0,
                'sort_order' => $sortOrder,
            ]
        );
    }

    /**
     * Update a department.
     */
    public function update_department(
        int $id,
        string $slug,
        string $name,
        string $emailAddress,
        bool $active,
        int $sortOrder
    ): mixed {
        return $this->update(
            self::DEPARTMENTS_TABLE,
            [
                'slug' => $slug,
                'name' => $name,
                'email_address' => $emailAddress,
                'active' => $active ? 1 : 0,
                'sort_order' => $sortOrder,
            ],
            'id = :id',
            ['id' => $id]
        );
    }

    /**
     * Delete a department.
     */
    public function delete_department(int $id): mixed
    {
        return $this->query(
            'DELETE FROM ' . self::DEPARTMENTS_TABLE . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    /**
     * Return Contact-owned end-user acknowledgement configuration.
     *
     * SMTP transport and sender identity belong to ChAoS MVC mailer::create().
     *
     * @return array<string, mixed>
     */
    public function get_config(): array
    {
        $row = $this->fetch(
            'SELECT confirmation_subject, confirmation_message'
            . ' FROM ' . self::CONFIG_TABLE
            . ' WHERE id = 1 LIMIT 1'
        );

        if (!is_array($row)) {
            return [
                'confirmation_subject' => '',
                'confirmation_message' => '',
            ];
        }

        return $row;
    }

    /**
     * Save Contact-owned end-user acknowledgement configuration.
     */
    public function save_config(
        string $confirmationSubject,
        string $confirmationMessage
    ): mixed {
        $data = [
            'confirmation_subject' => $confirmationSubject,
            'confirmation_message' => $confirmationMessage,
        ];

        $existing = $this->fetch(
            'SELECT id FROM ' . self::CONFIG_TABLE . ' WHERE id = 1 LIMIT 1'
        );

        if (!is_array($existing)) {
            return $this->insert(
                self::CONFIG_TABLE,
                array_merge(['id' => 1], $data)
            );
        }

        return $this->update(
            self::CONFIG_TABLE,
            $data,
            'id = :id',
            ['id' => 1]
        );
    }

    private function contacts_schema_valid(): bool
    {
        $required = [
            'id',
            'name',
            'email',
            'department',
            'subject',
            'message',
            'min_level',
            'reply_content',
            'status',
            'created_at',
            'updated_at',
        ];

        return $this->table_has_columns(self::CONTACTS_TABLE, $required);
    }

    private function departments_base_schema_valid(): bool
    {
        $required = [
            'id',
            'slug',
            'name',
            'active',
            'sort_order',
            'created_at',
            'updated_at',
        ];

        return $this->table_has_columns(self::DEPARTMENTS_TABLE, $required);
    }

    private function departments_schema_valid(): bool
    {
        return $this->departments_base_schema_valid()
            && $this->table_has_columns(self::DEPARTMENTS_TABLE, ['email_address']);
    }

    private function config_base_schema_valid(): bool
    {
        $required = [
            'id',
            'confirmation_subject',
            'confirmation_message',
            'created_at',
            'updated_at',
        ];

        return $this->table_has_columns(self::CONFIG_TABLE, $required);
    }

    private function config_has_legacy_sender_columns(): bool
    {
        return $this->table_has_columns(
            self::CONFIG_TABLE,
            ['sender_name', 'sender_email']
        );
    }

    private function config_has_partial_legacy_sender_columns(): bool
    {
        $hasSenderName = $this->table_has_columns(
            self::CONFIG_TABLE,
            ['sender_name']
        );
        $hasSenderEmail = $this->table_has_columns(
            self::CONFIG_TABLE,
            ['sender_email']
        );

        return $hasSenderName !== $hasSenderEmail;
    }

    private function table_exists(string $table): bool
    {
        $row = $this->fetch(
            'SELECT COUNT(*) AS table_count'
            . ' FROM information_schema.tables'
            . ' WHERE table_schema = DATABASE() AND table_name = :table_name',
            ['table_name' => $table]
        );

        return (int) ($row['table_count'] ?? 0) === 1;
    }

    /**
     * @param array<int, string> $requiredColumns
     */
    private function table_has_columns(string $table, array $requiredColumns): bool
    {
        $rows = $this->fetchAll(
            'SELECT column_name'
            . ' FROM information_schema.columns'
            . ' WHERE table_schema = DATABASE() AND table_name = :table_name',
            ['table_name' => $table]
        );

        $columns = [];

        foreach ($rows as $row) {
            $column = strtolower((string) ($row['column_name'] ?? ''));

            if ($column !== '') {
                $columns[] = $column;
            }
        }

        foreach ($requiredColumns as $requiredColumn) {
            if (!in_array(strtolower($requiredColumn), $columns, true)) {
                return false;
            }
        }

        return true;
    }

    private function execute_sql_file(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Required Contact SQL file is unavailable: ' . $path);
        }

        $sql = file_get_contents($path);

        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('Required Contact SQL file is empty: ' . $path);
        }

        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];

        foreach ($statements as $statement) {
            $statement = trim($statement);

            if ($statement !== '') {
                $this->query($statement);
            }
        }
    }
}

/* [End AI:GPT-5.6 Sol] */
/* [End AI:GPT-5.6 Sol] */
