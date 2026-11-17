<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Mapping;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support\TestDatabase;

/**
 * The schema the mapping builds on SQLite (the platform of the CI, ADR 0014): tables, exact columns, indexes and unique
 * constraints. A change of the schema is a change of the migrations of the host applications: it must be deliberate.
 */
final class DatabaseSchemaTest extends TestCase
{
    /**
     * Columns by table: name => nullable.
     */
    private const COLUMNS = [
        'urssaf_paje_active_mandates' => [
            'employer_number' => false,
            'mandate_id' => false,
            'tenant_id' => false,
        ],
        'urssaf_paje_declaration_records' => [
            'activity' => false,
            'created_at' => false,
            'employee_identification_kind' => false,
            'employee_number' => true,
            'employer_account_id' => true,
            'employer_number' => false,
            'errors' => false,
            'external_reference' => false,
            'fingerprint' => false,
            'id' => false,
            'last_attempt_at' => true,
            'outcome_unknown_reason' => true,
            'payment_date' => false,
            'period_end' => false,
            'period_start' => false,
            'result' => true,
            'state' => false,
            'submission_attempts' => false,
            'tenant_id' => false,
            'updated_at' => false,
            'version' => false,
        ],
        'urssaf_paje_employer_accounts' => [
            'birth_date' => false,
            'created_at' => false,
            'employer_number' => false,
            'external_reference' => false,
            'id' => false,
            'last_checked_at' => true,
            'last_verification' => true,
            'origin' => false,
            'stage' => false,
            'tenant_id' => false,
            'updated_at' => false,
            'version' => false,
        ],
        'urssaf_paje_mandates' => [
            'birth_date' => false,
            'cancelled_at' => true,
            'employer_account_id' => false,
            'employer_number' => false,
            'external_reference' => false,
            'id' => false,
            'origin' => false,
            'registered_at' => false,
            'state' => false,
            'tenant_id' => false,
            'updated_at' => false,
            'version' => false,
        ],
        'urssaf_paje_outbox' => [
            'aggregate_id' => false,
            'attempts' => false,
            'data' => false,
            'dispatched_at' => true,
            'event_id' => false,
            'last_error' => true,
            'occurred_at' => false,
            'payload' => false,
            'sequence' => false,
            'tenant_id' => false,
            'type' => false,
            'version' => false,
        ],
    ];

    /**
     * Indexes by table: name => [unique, columns].
     */
    private const INDEXES = [
        'urssaf_paje_active_mandates' => [
            'uniq_urssaf_paje_active_mandate_id' => [true, ['mandate_id']],
        ],
        'urssaf_paje_declaration_records' => [
            'idx_urssaf_paje_declaration_employer' => [false, ['tenant_id', 'employer_number', 'state']],
            'idx_urssaf_paje_declaration_stale' => [false, ['state', 'last_attempt_at']],
            'idx_urssaf_paje_declaration_state' => [false, ['tenant_id', 'state', 'updated_at']],
            'uniq_urssaf_paje_declaration_external_reference' => [true, ['tenant_id', 'external_reference']],
        ],
        'urssaf_paje_employer_accounts' => [
            'idx_urssaf_paje_employer_checked' => [false, ['tenant_id', 'last_checked_at']],
            'idx_urssaf_paje_employer_stage' => [false, ['tenant_id', 'stage']],
            'uniq_urssaf_paje_employer_external_reference' => [true, ['tenant_id', 'external_reference']],
            'uniq_urssaf_paje_employer_number' => [true, ['tenant_id', 'employer_number']],
        ],
        'urssaf_paje_mandates' => [
            'idx_urssaf_paje_mandate_employer' => [false, ['tenant_id', 'employer_number', 'registered_at']],
            'idx_urssaf_paje_mandate_state' => [false, ['tenant_id', 'state']],
        ],
        'urssaf_paje_outbox' => [
            'idx_urssaf_paje_outbox_pending' => [false, ['dispatched_at', 'sequence']],
            'uniq_urssaf_paje_outbox_event_id' => [true, ['event_id']],
        ],
    ];

    /**
     * @var list<Table>
     */
    private array $tables;

    protected function setUp(): void
    {
        $this->tables = SchemaReader::tables(TestDatabase::entityManager()->getConnection());
    }

    /**
     * @return iterable<string, array{string}> the expected tables
     */
    public static function tables(): iterable
    {
        foreach (array_keys(self::COLUMNS) as $name) {
            yield $name => [$name];
        }
    }

    public function testTheTablesAreExactlyTheExpectedOnesAndPrefixed(): void
    {
        $names = array_map(SchemaReader::tableName(...), $this->tables);
        sort($names);

        static::assertSame(array_keys(self::COLUMNS), $names);
        static::assertSame([], array_filter($names, static fn(string $name): bool => !str_starts_with($name, DoctrineIntegration::TABLE_PREFIX)));
    }

    public function testThereIsNoProgressTable(): void
    {
        static::assertSame([], array_filter(array_map(SchemaReader::tableName(...), $this->tables), static fn(string $name): bool => str_contains($name, 'progress')));
    }

    #[DataProvider('tables')]
    public function testTheTableHoldsExactlyTheExpectedColumns(string $name): void
    {
        $table = $this->table($name);
        $columns = array_combine(
            array_map(SchemaReader::columnName(...), array_values($table->getColumns())),
            array_map(static fn(Column $column): bool => !$column->getNotnull(), array_values($table->getColumns())),
        );
        ksort($columns);

        static::assertSame(self::COLUMNS[$name], $columns);
    }

    #[DataProvider('tables')]
    public function testTheTableHoldsExactlyTheExpectedIndexesAndUniqueConstraints(string $name): void
    {
        static::assertSame(self::INDEXES[$name], SchemaReader::indexes($this->table($name)));
    }

    #[DataProvider('identifiedTables')]
    public function testTheTableHasAPrimaryKeyOnItsIdentifier(string $name, string $identifier): void
    {
        static::assertSame([$identifier], SchemaReader::primaryKey($this->table($name)));
    }

    /**
     * @return iterable<string, array{string, string}> table, identifier column
     */
    public static function identifiedTables(): iterable
    {
        yield 'employer accounts' => ['urssaf_paje_employer_accounts', 'id'];
        yield 'mandates' => ['urssaf_paje_mandates', 'id'];
        yield 'declaration records' => ['urssaf_paje_declaration_records', 'id'];
        yield 'outbox' => ['urssaf_paje_outbox', 'sequence'];
    }

    public function testTheActiveMandateKeyIsTheTenantAndTheEmployerNumber(): void
    {
        static::assertSame(['tenant_id', 'employer_number'], SchemaReader::primaryKey($this->table('urssaf_paje_active_mandates')));
    }

    private function table(string $name): Table
    {
        foreach ($this->tables as $table) {
            if (SchemaReader::tableName($table) === $name) {
                return $table;
            }
        }

        static::fail('Unknown table ' . $name);
    }
}
