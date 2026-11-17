<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Mapping;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\Assert;

/**
 * Reads the schema and the rows of a database, for the schema and personal data tests.
 */
final class SchemaReader
{
    private function __construct() {}

    /**
     * @return list<Table>
     */
    public static function tables(Connection $connection): array
    {
        return $connection->createSchemaManager()->introspectTables();
    }

    public static function tableName(Table $table): string
    {
        return $table->getObjectName()->getUnqualifiedName()->getValue();
    }

    public static function columnName(Column $column): string
    {
        return $column->getObjectName()->getIdentifier()->getValue();
    }

    /**
     * @return array<string, list<string>> column names by table name
     */
    public static function columnNames(Connection $connection): array
    {
        $columns = [];
        foreach (self::tables($connection) as $table) {
            $columns[self::tableName($table)] = array_values(array_map(self::columnName(...), $table->getColumns()));
        }

        return $columns;
    }

    /**
     * @return list<string> the columns of the primary key
     */
    public static function primaryKey(Table $table): array
    {
        $primary = $table->getPrimaryKeyConstraint();
        Assert::assertNotNull($primary);

        return array_map(static fn(UnqualifiedName $column): string => $column->getIdentifier()->getValue(), $primary->getColumnNames());
    }

    /**
     * @return array<string, array{bool, list<string>}> unique flag and columns by index name (the named indexes only)
     */
    public static function indexes(Table $table): array
    {
        $indexes = [];
        foreach ($table->getIndexes() as $index) {
            $name = $index->getObjectName()->getIdentifier()->getValue();
            if (str_starts_with($name, 'idx_') || str_starts_with($name, 'uniq_')) {
                $indexes[$name] = [
                    $index->getType() === IndexType::UNIQUE,
                    array_map(static fn(IndexedColumn $column): string => $column->getColumnName()->getIdentifier()->getValue(), $index->getIndexedColumns()),
                ];
            }
        }
        ksort($indexes);

        return $indexes;
    }

    /**
     * @return list<string> every non-null value of every column of every table, as a string
     */
    public static function storedValues(Connection $connection): array
    {
        $values = [];
        foreach (array_keys(self::columnNames($connection)) as $table) {
            foreach ($connection->fetchAllAssociative('SELECT * FROM ' . $table) as $row) {
                $values = [...$values, ...self::strings($row)];
            }
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<string>
     */
    private static function strings(array $row): array
    {
        $strings = [];
        // @mago-expect analysis:mixed-assignment A fetched column value is untyped; it is checked to be a scalar below.
        foreach (array_filter($row, static fn(mixed $value): bool => $value !== null) as $value) {
            Assert::assertIsScalar($value);
            $strings[] = (string) $value;
        }

        return $strings;
    }
}
