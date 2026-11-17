<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Mapping;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaValidator;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract\ContractSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support\TestDatabase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\RecordSamples;

/**
 * Mapping scenarios (validity, round trips through the entity manager only, UTC instants, stored codes): one method per
 * scenario.
 *
 * @mago-expect lint:too-many-methods
 */
final class DoctrineMappingTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = TestDatabase::entityManager();
    }

    public function testTheMappingIsValidAndInSyncWithTheSchema(): void
    {
        $validator = new SchemaValidator($this->entityManager);

        static::assertSame([], $validator->validateMapping());
        static::assertTrue($validator->schemaInSyncWithMetadata());
    }

    public function testTheVersionIsAnOptimisticLockColumnOfTheThreeAggregates(): void
    {
        $account = ContractSamples::account(1);
        $mandate = ContractSamples::mandate(1);
        $record = ContractSamples::record(1, DeclarationState::Pending);
        $this->entityManager->persist($account);
        $this->entityManager->persist($mandate);
        $this->entityManager->persist($record);
        $this->entityManager->flush();

        static::assertSame(1, $this->version('urssaf_paje_employer_accounts'));
        static::assertSame(1, $this->version('urssaf_paje_mandates'));
        static::assertSame(1, $this->version('urssaf_paje_declaration_records'));

        $account->markMandateRegistered(ContractSamples::at('2026-11-10T10:00:00Z'));
        $mandate->cancel(ContractSamples::at('2026-11-10T10:00:00Z'));
        $record->startSubmission(ContractSamples::at('2026-11-10T10:00:00Z'));
        $this->entityManager->flush();

        static::assertSame(2, $this->version('urssaf_paje_employer_accounts'));
        static::assertSame(2, $this->version('urssaf_paje_mandates'));
        static::assertSame(2, $this->version('urssaf_paje_declaration_records'));
    }

    #[DataProviderExternal(DoctrineMappingCases::class, 'accounts')]
    public function testAnAccountIsReadBackIdentically(string $case): void
    {
        $account = DoctrineMappingSamples::account($case);

        $read = $this->roundTrip($account, EmployerAccount::class, $account->id());

        static::assertNotSame($account, $read);
        static::assertEquals($account, $read);
        static::assertSame([], $read->releaseEvents());
        static::assertTrue($read->tracking()->equals($account->tracking()));
        static::assertSame($account->employerNumber()->value(), $read->employerNumber()->value());
        static::assertSame($account->birthDate()->toString(), $read->birthDate()->toString());
        static::assertSame($account->origin(), $read->origin());
        static::assertEquals($account->lastVerification(), $read->lastVerification());
    }

    #[DataProviderExternal(DoctrineMappingCases::class, 'mandates')]
    public function testAMandateIsReadBackIdentically(string $case): void
    {
        $mandate = DoctrineMappingSamples::mandate($case);

        $read = $this->roundTrip($mandate, Mandate::class, $mandate->id());

        static::assertNotSame($mandate, $read);
        static::assertEquals($mandate, $read);
        static::assertSame([], $read->releaseEvents());
        static::assertSame($mandate->state(), $read->state());
        static::assertSame($mandate->origin(), $read->origin());
        static::assertSame($mandate->employer()->birthDate()->toString(), $read->employer()->birthDate()->toString());
        static::assertTrue($mandate->employerAccountId()->equals($read->employerAccountId()));
        static::assertTrue($mandate->timeline()->equals($read->timeline()));
    }

    #[DataProviderExternal(DoctrineMappingCases::class, 'records')]
    public function testARecordIsReadBackIdentically(string $case): void
    {
        $record = DoctrineMappingSamples::record($case);

        $read = $this->roundTrip($record, DeclarationRecord::class, $record->id());

        static::assertNotSame($record, $read);
        static::assertEquals($record, $read);
        static::assertSame([], $read->releaseEvents());
        static::assertSame($record->state(), $read->state());
        static::assertSame($record->attempts(), $read->attempts());
        static::assertSame($record->outcomeUnknownReason(), $read->outcomeUnknownReason());
        static::assertSame($record->snapshot()->activity(), $read->snapshot()->activity());
        static::assertSame($record->snapshot()->employeeIdentificationKind(), $read->snapshot()->employeeIdentificationKind());
        static::assertTrue($record->snapshot()->period()->equals($read->snapshot()->period()));
        static::assertEquals($record->errors(), $read->errors());
        static::assertEquals($record->result(), $read->result());
        static::assertEquals($record->employerAccountId(), $read->employerAccountId());
    }

    public function testADeclaredRecordKeepsItsResultAndDropsTheChildChecks(): void
    {
        $record = ContractSamples::record(1, DeclarationState::Declared);
        $this->entityManager->persist($record);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $read = $this->entityManager->find(DeclarationRecord::class, $record->id());

        static::assertInstanceOf(DeclarationRecord::class, $read);
        $result = $read->result();
        static::assertNotNull($result);
        static::assertSame('REF-00001', $result->documentReference());
        static::assertSame([], $result->childChecks());
        static::assertCount(2, $result->messages());
        $stored = $this->entityManager->getConnection()->fetchOne('SELECT result FROM urssaf_paje_declaration_records');
        static::assertIsString($stored);
        static::assertStringNotContainsString('JEAN', $stored);
        static::assertSame(RecordSamples::result('REF-00001')->messages()[0]->code(), $result->messages()[0]->code());
    }

    public function testTheSnapshotColumnsAreStoredWithTheirCodes(): void
    {
        foreach (['home-care', 'nir', 'administrative'] as $number => $case) {
            $record = DoctrineMappingSamples::record($case);
            $this->entityManager->persist(self::renumbered($record, $number + 1));
        }
        $this->entityManager->flush();

        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT employer_number, activity, employee_identification_kind, employee_number, period_start, period_end, payment_date, length(fingerprint) AS fingerprint_length FROM urssaf_paje_declaration_records ORDER BY id',
        );

        static::assertCount(3, $rows);
        static::assertSame('Y1869005780009', $rows[0]['employer_number']);
        static::assertSame(['home_care', 'number', 'Y2926822480004'], [$rows[0]['activity'], $rows[0]['employee_identification_kind'], $rows[0]['employee_number']]);
        static::assertSame(['childminder', 'nir', null], [$rows[1]['activity'], $rows[1]['employee_identification_kind'], $rows[1]['employee_number']]);
        static::assertSame(['home_care', 'administrative_data', null], [$rows[2]['activity'], $rows[2]['employee_identification_kind'], $rows[2]['employee_number']]);
        static::assertSame(['2026-10-01', '2026-10-31', '2026-11-05'], [$rows[2]['period_start'], $rows[2]['period_end'], $rows[2]['payment_date']]);
        static::assertIsInt($rows[2]['fingerprint_length']);
        static::assertSame(64, $rows[2]['fingerprint_length']);
    }

    public function testTheEmployerIdentificationIsStoredInTheAccountAndTheMandateWithTheSameColumns(): void
    {
        $this->entityManager->persist(ContractSamples::account(1));
        $this->entityManager->persist(ContractSamples::mandate(1));
        $this->entityManager->flush();
        $connection = $this->entityManager->getConnection();

        static::assertSame(
            ['employer_number' => 'Y1869005780009', 'birth_date' => '1975-01-30'],
            $connection->fetchAssociative('SELECT employer_number, birth_date FROM urssaf_paje_employer_accounts'),
        );
        static::assertSame(
            ['employer_number' => 'Y1869005780009', 'birth_date' => '1975-01-30', 'employer_account_id' => 'account-1', 'origin' => 'registered', 'state' => 'registered'],
            $connection->fetchAssociative('SELECT employer_number, birth_date, employer_account_id, origin, state FROM urssaf_paje_mandates'),
        );
    }

    public function testAbsentGroupsAreStoredAsSqlNull(): void
    {
        $this->entityManager->persist(ContractSamples::account(1));
        $this->entityManager->persist(ContractSamples::mandate(1));
        $this->entityManager->persist(ContractSamples::record(1, DeclarationState::Pending));
        $this->entityManager->flush();
        $connection = $this->entityManager->getConnection();

        static::assertNull($connection->fetchOne('SELECT last_verification FROM urssaf_paje_employer_accounts'));
        static::assertNull($connection->fetchOne('SELECT last_checked_at FROM urssaf_paje_employer_accounts'));
        static::assertNull($connection->fetchOne('SELECT cancelled_at FROM urssaf_paje_mandates'));
        static::assertNull($connection->fetchOne('SELECT result FROM urssaf_paje_declaration_records'));
        static::assertNull($connection->fetchOne('SELECT last_attempt_at FROM urssaf_paje_declaration_records'));
        static::assertNull($connection->fetchOne('SELECT outcome_unknown_reason FROM urssaf_paje_declaration_records'));
        static::assertNull($connection->fetchOne('SELECT employer_account_id FROM urssaf_paje_declaration_records'));
        static::assertSame('[]', $connection->fetchOne('SELECT errors FROM urssaf_paje_declaration_records'));
    }

    public function testInstantsAreStoredInUtcAndBirthDatesAsCalendarDatesWhateverTheDefaultTimeZone(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
        try {
            $account = ContractSamples::account(1, EmployerStage::MandateRegistered, ContractSamples::TENANT, '2026-11-10T23:30:00+01:00');
            $this->entityManager->persist($account);
            $this->entityManager->flush();
            $this->entityManager->clear();
            $read = $this->entityManager->find(EmployerAccount::class, $account->id());
            $stored = $this->entityManager->getConnection()->fetchAssociative('SELECT created_at, updated_at, birth_date FROM urssaf_paje_employer_accounts');
        } finally {
            date_default_timezone_set($previous);
        }

        static::assertEquals($account, $read);
        static::assertSame(
            ['created_at' => '2026-11-10 22:30:00', 'updated_at' => '2026-11-10 22:30:00', 'birth_date' => '1975-01-30'],
            $stored,
        );
    }

    public function testTheUniqueConstraintsAreEnforcedByTheSchema(): void
    {
        $this->entityManager->persist(ContractSamples::account(1));
        $this->entityManager->flush();

        $sameEmployerNumber = EmployerAccount::track(
            EmployerAccountId::fromString('account-2'),
            ContractSamples::tenant(),
            ExternalReference::fromString('employer-2'),
            ContractSamples::employer(1),
            ContractSamples::at(),
        );
        $this->entityManager->persist($sameEmployerNumber);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function testTheSameEmployerNumberMayExistInAnotherTenant(): void
    {
        $this->entityManager->persist(ContractSamples::account(1));
        $this->entityManager->persist(ContractSamples::accountOfEmployer(1, 2, EmployerStage::Tracked, ContractSamples::OTHER_TENANT));
        $this->entityManager->flush();

        static::assertSame(2, $this->rowCount('urssaf_paje_employer_accounts'));
    }

    public function testTheExternalReferenceOfARecordIsUniquePerTenant(): void
    {
        $this->entityManager->persist(ContractSamples::pendingRecordOfReference(1, 1));
        $this->entityManager->persist(ContractSamples::pendingRecordOfReference(1, 2, ContractSamples::OTHER_TENANT));
        $this->entityManager->flush();
        $this->entityManager->persist(ContractSamples::pendingRecordOfReference(1, 3));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function testSeveralMandatesMayShareTheEmployer(): void
    {
        $this->entityManager->persist(ContractSamples::mandate(1));
        $this->entityManager->persist(ContractSamples::mandateOfEmployer(1, 2));
        $this->entityManager->flush();

        static::assertSame(2, $this->rowCount('urssaf_paje_mandates'));
    }

    private function rowCount(string $table): int
    {
        $count = $this->entityManager->getConnection()->fetchOne('SELECT count(*) FROM ' . $table);
        static::assertIsNumeric($count);

        return (int) $count;
    }

    private function version(string $table): int
    {
        $version = $this->entityManager->getConnection()->fetchOne('SELECT version FROM ' . $table);
        static::assertIsNumeric($version);

        return (int) $version;
    }

    private static function renumbered(DeclarationRecord $record, int $number): DeclarationRecord
    {
        $renumbered = DeclarationRecord::record(
            DeclarationRecordId::fromString('record-' . $number),
            $record->tenantId(),
            ExternalReference::fromString('declaration-' . $number),
            $record->snapshot(),
            $record->createdAt(),
            null,
        );
        $renumbered->releaseEvents();

        return $renumbered;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function roundTrip(object $aggregate, string $class, EmployerAccountId|MandateId|DeclarationRecordId $id): object
    {
        $this->entityManager->persist($aggregate);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $read = $this->entityManager->find($class, $id);

        static::assertInstanceOf($class, $read);

        return $read;
    }
}
