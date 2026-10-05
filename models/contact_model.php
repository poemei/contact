<?php

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-10-05 UTC] */

/**
 * Contact Model
 *
 * Canonical ChAoS MVC module model for Contact-owned schema lifecycle,
 * data lifecycle, and Contact operations.
 */
final class contact_model extends model
{
    private const TABLES = [
        'contact_schema',
        'contacts',
        'contact_departments',
        'contact_config',
    ];

    private const STATE_TABLE = 'contact_schema';
    private const CONTACTS_TABLE = 'contacts';
    private const DEPARTMENTS_TABLE = 'contact_departments';
    private const CONFIG_TABLE = 'contact_config';

    public function getModuleInformation(): array
    {
        return [
            'name' => 'Contact',
            'slug' => 'contact',
            'version' => $this->moduleVersion(),
            'schema_version' => $this->targetVersion(),
            'tables' => self::TABLES,
        ];
    }

    /*
     * -----------------------------------------------------------------
     * Module / Data Lifecycle
     * -----------------------------------------------------------------
     */

    public function databaseState(): string
    {
        if (!$this->tableExists(self::CONTACTS_TABLE)) {
            return 'missing';
        }

        if (!$this->tableExists(self::STATE_TABLE)) {
            return $this->legacySchemaVersion() !== null
                ? 'update'
                : 'invalid';
        }

        foreach (self::TABLES as $table) {
            if (!$this->tableExists($table)) {
                return 'invalid';
            }
        }

        $current = $this->schemaVersion();
        $target = $this->targetVersion();

        if ($current === null || $target === '') {
            return 'invalid';
        }

        if ($current === $target) {
            return 'current';
        }

        return $this->migrationPath($current, $target) !== []
            ? 'update'
            : 'invalid';
    }

    public function installSchema(): void
    {
        if ($this->databaseState() !== 'missing') {
            throw new RuntimeException(
                'Contact schema installation is not available in the current database state.'
            );
        }

        $this->executeSqlFile(__DIR__ . '/../sql/schema.sql');

        if ($this->databaseState() !== 'current') {
            throw new RuntimeException(
                'Contact schema installation did not produce the expected current schema.'
            );
        }
    }

    public function updateSchema(): void
    {
        if ($this->databaseState() !== 'update') {
            throw new RuntimeException(
                'Contact schema update is not available in the current database state.'
            );
        }

        $current = $this->schemaVersion();

        if ($current === null) {
            $current = $this->legacySchemaVersion();
        }

        $target = $this->targetVersion();

        if ($current === null || $target === '') {
            throw new RuntimeException(
                'Contact could not determine a supported schema migration source.'
            );
        }

        $path = $this->migrationPath($current, $target);

        if ($path === []) {
            throw new RuntimeException(
                'No valid Contact schema migration path exists.'
            );
        }

        foreach ($path as $patch) {
            $this->executeSqlFile($patch);
        }

        if ($this->databaseState() !== 'current') {
            throw new RuntimeException(
                'Contact schema update did not produce the expected current schema.'
            );
        }
    }

    /**
     * Delete operational inquiry records while preserving Contact schema,
     * department routing, and acknowledgement configuration.
     */
    public function deleteData(): void
    {
        if ($this->databaseState() !== 'current') {
            throw new RuntimeException(
                'Contact data cannot be deleted until the database schema is current.'
            );
        }

        $this->query('DELETE FROM `' . self::CONTACTS_TABLE . '`');
    }

    /*
     * -----------------------------------------------------------------
     * Contact Operations
     * -----------------------------------------------------------------
     */

    public function createInquiry(array $data): mixed
    {
        return $this->insert(self::CONTACTS_TABLE, $data);
    }

    public function getVisibleInquiries(int $level): array
    {
        return $this->fetchAll(
            'SELECT * FROM `' . self::CONTACTS_TABLE . '` '
            . 'WHERE `min_level` <= :level '
            . 'ORDER BY `min_level` DESC, `created_at` ASC',
            ['level' => $level]
        ) ?: [];
    }

    public function getInquiry(int $id): array|false
    {
        return $this->fetch(
            'SELECT * FROM `' . self::CONTACTS_TABLE . '` '
            . 'WHERE `id` = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public function updateTicket(int $id, array $data): mixed
    {
        return $this->update(
            self::CONTACTS_TABLE,
            $data,
            'id = :target_id',
            ['target_id' => $id]
        );
    }

    public function deleteTicket(int $id): mixed
    {
        return $this->query(
            'DELETE FROM `' . self::CONTACTS_TABLE . '` WHERE `id` = :id',
            ['id' => $id]
        );
    }

    public function getActiveDepartments(): array
    {
        return $this->fetchAll(
            'SELECT `id`, `slug`, `name`, `email_address`, `sort_order` '
            . 'FROM `' . self::DEPARTMENTS_TABLE . '` '
            . 'WHERE `active` = 1 '
            . 'AND `email_address` IS NOT NULL '
            . 'AND `email_address` <> \'\' '
            . 'ORDER BY `sort_order` ASC, `name` ASC'
        ) ?: [];
    }

    public function getDepartments(): array
    {
        return $this->fetchAll(
            'SELECT `id`, `slug`, `name`, `email_address`, `active`, '
            . '`sort_order`, `created_at`, `updated_at` '
            . 'FROM `' . self::DEPARTMENTS_TABLE . '` '
            . 'ORDER BY `sort_order` ASC, `name` ASC'
        ) ?: [];
    }

    public function getActiveDepartment(string $slug): array|false
    {
        return $this->fetch(
            'SELECT `id`, `slug`, `name`, `email_address` '
            . 'FROM `' . self::DEPARTMENTS_TABLE . '` '
            . 'WHERE `slug` = :slug '
            . 'AND `active` = 1 '
            . 'AND `email_address` IS NOT NULL '
            . 'AND `email_address` <> \'\' LIMIT 1',
            ['slug' => $slug]
        );
    }

    public function createDepartment(
        string $slug,
        string $name,
        string $emailAddress,
        bool $active,
        int $sortOrder
    ): mixed {
        return $this->insert(self::DEPARTMENTS_TABLE, [
            'slug' => $slug,
            'name' => $name,
            'email_address' => $emailAddress,
            'active' => $active ? 1 : 0,
            'sort_order' => $sortOrder,
        ]);
    }

    public function updateDepartment(
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

    public function deleteDepartment(int $id): mixed
    {
        return $this->query(
            'DELETE FROM `' . self::DEPARTMENTS_TABLE . '` WHERE `id` = :id',
            ['id' => $id]
        );
    }

    public function getConfig(): array
    {
        $row = $this->fetch(
            'SELECT `confirmation_subject`, `confirmation_message` '
            . 'FROM `' . self::CONFIG_TABLE . '` WHERE `id` = 1 LIMIT 1'
        );

        return is_array($row) ? $row : [
            'confirmation_subject' => '',
            'confirmation_message' => '',
        ];
    }

    public function saveConfig(
        string $confirmationSubject,
        string $confirmationMessage
    ): mixed {
        $data = [
            'confirmation_subject' => $confirmationSubject,
            'confirmation_message' => $confirmationMessage,
        ];

        $existing = $this->fetch(
            'SELECT `id` FROM `' . self::CONFIG_TABLE . '` WHERE `id` = 1 LIMIT 1'
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

    /*
     * -----------------------------------------------------------------
     * Lifecycle Helpers
     * -----------------------------------------------------------------
     */

    /**
     * Deterministically identify supported Contact schemas that predate
     * contact_schema. These signatures come from Contact's packaged schema
     * and exact historical migration files.
     */
    private function legacySchemaVersion(): ?string
    {
        if (!$this->tableExists(self::CONTACTS_TABLE)) {
            return null;
        }

        if (!$this->tableExists(self::DEPARTMENTS_TABLE)) {
            return '1.1.0';
        }

        if (!$this->columnExists(self::DEPARTMENTS_TABLE, 'email_address')) {
            return '1.2.0';
        }

        if (!$this->tableExists(self::CONFIG_TABLE)) {
            return null;
        }

        if (
            $this->columnExists(self::CONFIG_TABLE, 'sender_name')
            && $this->columnExists(self::CONFIG_TABLE, 'sender_email')
        ) {
            return '1.3.0';
        }

        if (
            !$this->columnExists(self::CONFIG_TABLE, 'sender_name')
            && !$this->columnExists(self::CONFIG_TABLE, 'sender_email')
            && $this->columnExists(self::CONFIG_TABLE, 'confirmation_subject')
            && $this->columnExists(self::CONFIG_TABLE, 'confirmation_message')
        ) {
            return '1.3.1';
        }

        return null;
    }

    private function migrationPath(string $current, string $target): array
    {
        if (!$this->validVersion($current) || !$this->validVersion($target)) {
            return [];
        }

        if ($current === $target) {
            return [];
        }

        $transitions = [
            '1.1.0' => '1.2.0',
            '1.2.0' => '1.3.0',
            '1.3.0' => '1.3.1',
            '1.3.1' => '1.4.0',
        ];

        $path = [];
        $version = $current;
        $visited = [];

        while ($version !== $target) {
            if (isset($visited[$version]) || !isset($transitions[$version])) {
                return [];
            }

            $visited[$version] = true;
            $next = $transitions[$version];
            $patch = $this->patchFile($version, $next);

            if ($patch === null) {
                return [];
            }

            $path[] = $patch;
            $version = $next;
        }

        return $path;
    }

    private function schemaVersion(): ?string
    {
        if (!$this->tableExists(self::STATE_TABLE)) {
            return null;
        }

        $row = $this->fetch(
            'SELECT `schema_version` FROM `' . self::STATE_TABLE . '` '
            . 'WHERE `id` = 1 LIMIT 1'
        );

        $version = is_array($row)
            ? trim((string) ($row['schema_version'] ?? ''))
            : '';

        return $this->validVersion($version) ? $version : null;
    }

    private function moduleMetadata(): array
    {
        $raw = file_get_contents(__DIR__ . '/../module.json');
        $metadata = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($metadata) ? $metadata : [];
    }

    private function moduleVersion(): string
    {
        return trim((string) ($this->moduleMetadata()['version'] ?? ''));
    }

    private function targetVersion(): string
    {
        $version = trim((string) ($this->moduleMetadata()['schema_version'] ?? ''));
        return $this->validVersion($version) ? $version : '';
    }

    private function validVersion(string $version): bool
    {
        return preg_match(
            '/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][0-9A-Za-z.-]+)?$/',
            $version
        ) === 1;
    }

    private function patchFile(string $current, string $target): ?string
    {
        if (!$this->validVersion($current) || !$this->validVersion($target)) {
            return null;
        }

        $file = __DIR__ . '/../sql/patches/' . $current . '-to-' . $target . '.sql';

        return is_file($file) && !is_link($file)
            ? $file
            : null;
    }

    private function tableExists(string $table): bool
    {
        $row = $this->fetch(
            'SELECT COUNT(*) AS `table_count` FROM `information_schema`.`tables` '
            . 'WHERE `table_schema` = DATABASE() AND `table_name` = :table_name',
            ['table_name' => $table]
        );

        return (int) ($row['table_count'] ?? 0) === 1;
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }

        $row = $this->fetch(
            'SELECT COUNT(*) AS `column_count` FROM `information_schema`.`columns` '
            . 'WHERE `table_schema` = DATABASE() '
            . 'AND `table_name` = :table_name '
            . 'AND `column_name` = :column_name',
            [
                'table_name' => $table,
                'column_name' => $column,
            ]
        );

        return (int) ($row['column_count'] ?? 0) === 1;
    }

    private function executeSqlFile(string $file): void
    {
        if (!is_file($file) || is_link($file)) {
            throw new RuntimeException('Contact SQL file could not be read.');
        }

        $sql = file_get_contents($file);

        if (!is_string($sql) || trim($sql) === '') {
            throw new RuntimeException('Contact SQL file could not be read.');
        }

        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);

        if (!is_array($statements)) {
            throw new RuntimeException('Contact SQL file could not be parsed.');
        }

        foreach ($statements as $statement) {
            $statement = trim($statement);

            if ($statement !== '') {
                $this->query($statement);
            }
        }
    }
}

/* [End AI:GPT-5.6 Sol] */
