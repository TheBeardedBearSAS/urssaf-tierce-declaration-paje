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

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationSnapshot;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract\ContractSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support\TestDatabase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\RecordSamples;

/**
 * ADR 0007: the NIR, the employee names, birth data and address, and the child names and birth dates are never
 * persisted. The only birth date of the database is the employer's, in the accounts and in the mandates (ADR 0007, lot
 * 4a amendment).
 */
final class PersonalDataColumnsTest extends TestCase
{
    private const NIR = '275037512345678';

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = TestDatabase::entityManager();
    }

    public function testNoColumnIsNamedAfterPersonalData(): void
    {
        $columns = array_merge(...array_values(SchemaReader::columnNames($this->entityManager->getConnection())));

        static::assertGreaterThan(40, \count($columns));
        static::assertSame([], array_filter($columns, static fn(string $name): bool => preg_match('/nir|name|address|city|postal|country|department|civility|child|iban|bic|email|phone|salary|hours/i', $name) === 1));
    }

    public function testTheEmployerBirthDateIsTheOnlyBirthDateColumn(): void
    {
        $birthColumns = array_filter(array_map(
            static fn(array $names): array => array_values(array_filter($names, static fn(string $name): bool => str_contains($name, 'birth'))),
            SchemaReader::columnNames($this->entityManager->getConnection()),
        ));
        ksort($birthColumns);

        static::assertSame(
            ['urssaf_paje_employer_accounts' => ['birth_date'], 'urssaf_paje_mandates' => ['birth_date']],
            $birthColumns,
        );
    }

    public function testTheEmployeeIsIdentifiedByItsKindAndItsNumberOnly(): void
    {
        $records = SchemaReader::columnNames($this->entityManager->getConnection())['urssaf_paje_declaration_records'];

        static::assertContains('employee_identification_kind', $records);
        static::assertContains('employee_number', $records);
        static::assertSame([], array_values(array_filter($records, static fn(string $column): bool => str_starts_with($column, 'employee_') && !\in_array($column, ['employee_identification_kind', 'employee_number'], true))));
    }

    public function testNothingPersonalReachesAnyRowOfTheDatabase(): void
    {
        $declaration = RecordSamples::childminder(EmployeeByNir::of(Nir::of(self::NIR), RecordSamples::date('1990-03-04')));
        $record = DeclarationRecord::record(
            DeclarationRecordId::fromString('record-1'),
            ContractSamples::tenant(),
            ExternalReference::fromString('declaration-1'),
            DeclarationSnapshot::fromDeclaration(ContractSamples::employerNumber(1), $declaration),
            ContractSamples::at(),
            null,
        );
        $record->startSubmission(ContractSamples::at());
        $record->markDeclared(RecordSamples::result('REF-001'), ContractSamples::at());
        $this->entityManager->persist($record);
        $this->entityManager->persist(ContractSamples::account(1));
        $this->entityManager->persist(ContractSamples::mandate(1));
        $this->entityManager->flush();

        $stored = SchemaReader::storedValues($this->entityManager->getConnection());

        static::assertContains('Y1869005780009', $stored);
        static::assertContains('1975-01-30', $stored);
        foreach ($stored as $value) {
            foreach ([self::NIR, 'JEAN', 'DUPONT', '2015-01-12', '1990-03-04'] as $personal) {
                static::assertStringNotContainsString($personal, $value);
            }
        }
        static::assertSame(DeclarationState::Declared, $record->state());
    }
}
