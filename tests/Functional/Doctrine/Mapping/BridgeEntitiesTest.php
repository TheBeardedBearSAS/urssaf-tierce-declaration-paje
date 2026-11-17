<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Mapping;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Entity\ActiveMandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Entity\OutboxMessage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\IntegrationEventCodec;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\MandateCancelled;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract\ContractSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support\TestDatabase;

/**
 * The two entities the bridge maps for its own tables: rows written with DBAL, never through the ORM (ADR 0014).
 */
final class BridgeEntitiesTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = TestDatabase::entityManager()->getConnection();
    }

    public function testAnActiveMandateIsTheKeyRowOfItsMandate(): void
    {
        $mandate = ContractSamples::mandate(1);

        $row = ActiveMandate::of($mandate)->row();

        static::assertSame(['tenant_id', 'employer_number', 'mandate_id'], array_keys($row));
        static::assertSame($mandate->tenantId(), $row['tenant_id']);
        static::assertSame($mandate->employerNumber(), $row['employer_number']);
        static::assertSame($mandate->id(), $row['mandate_id']);
        static::assertSame(array_keys($row), array_keys(ActiveMandate::TYPES));
    }

    public function testTheKeyRowIsWrittenWithDbalAndTheEmployerHasOneKeyRowPerTenant(): void
    {
        $this->connection->insert('urssaf_paje_active_mandates', ActiveMandate::of(ContractSamples::mandate(1))->row(), ActiveMandate::TYPES);

        static::assertSame(
            ['tenant_id' => 'tenant-1', 'employer_number' => 'Y1869005780009', 'mandate_id' => 'mandate-1'],
            $this->connection->fetchAssociative('SELECT tenant_id, employer_number, mandate_id FROM urssaf_paje_active_mandates'),
        );
        $this->connection->insert('urssaf_paje_active_mandates', ActiveMandate::of(ContractSamples::mandateOfEmployer(1, 2, MandateState::Registered, ContractSamples::OTHER_TENANT))->row(), ActiveMandate::TYPES);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->connection->insert('urssaf_paje_active_mandates', ActiveMandate::of(ContractSamples::mandateOfEmployer(1, 3))->row(), ActiveMandate::TYPES);
    }

    public function testAMandateHasAtMostOneKeyRow(): void
    {
        $this->connection->insert('urssaf_paje_active_mandates', ActiveMandate::of(ContractSamples::mandate(1))->row(), ActiveMandate::TYPES);
        $other = ActiveMandate::of(ContractSamples::mandate(2))->row();
        $other['mandate_id'] = ContractSamples::mandate(1)->id();

        $this->expectException(UniqueConstraintViolationException::class);
        $this->connection->insert('urssaf_paje_active_mandates', $other, ActiveMandate::TYPES);
    }

    public function testAnOutboxMessageHoldsEveryColumnOfTheTableButTheSequence(): void
    {
        $event = new MandateCancelled('8f7e9b0e-5f0a-4c53-9a53-1d2b0c3d4e5f', '2026-11-10T09:00:00Z', 'tenant-1', 'mandate-1', 'employer-1', 'account-1');

        $row = OutboxMessage::of($event)->row();

        $columns = SchemaReader::columnNames($this->connection)['urssaf_paje_outbox'];
        sort($columns);
        $names = array_keys($row);
        sort($names);
        static::assertSame(array_values(array_diff($columns, ['sequence'])), $names);
        static::assertSame(
            [
                'event_id' => '8f7e9b0e-5f0a-4c53-9a53-1d2b0c3d4e5f',
                'type' => 'urssaf.paje.mandate.cancelled',
                'version' => 1,
                'tenant_id' => 'tenant-1',
                'aggregate_id' => 'mandate-1',
                'occurred_at' => '2026-11-10T09:00:00Z',
                'payload' => $event->toArray(),
                'data' => IntegrationEventCodec::encode($event),
                'dispatched_at' => null,
                'attempts' => 0,
                'last_error' => null,
            ],
            $row,
        );
    }

    public function testAnOutboxMessageIsWrittenWithDbalAsAPendingEntry(): void
    {
        $event = new MandateCancelled('8f7e9b0e-5f0a-4c53-9a53-1d2b0c3d4e5f', '2026-11-10T09:00:00Z', 'tenant-1', 'mandate-1', 'employer-1', 'account-1');

        $this->connection->insert('urssaf_paje_outbox', OutboxMessage::of($event)->row(), OutboxMessage::TYPES);

        static::assertSame(
            ['sequence' => 1, 'event_id' => '8f7e9b0e-5f0a-4c53-9a53-1d2b0c3d4e5f', 'dispatched_at' => null, 'attempts' => 0],
            $this->connection->fetchAssociative('SELECT sequence, event_id, dispatched_at, attempts FROM urssaf_paje_outbox'),
        );
        $payload = $this->connection->fetchOne('SELECT payload FROM urssaf_paje_outbox');
        static::assertIsString($payload);
        static::assertSame($event->toArray(), json_decode($payload, true, 8, \JSON_THROW_ON_ERROR));
        $this->expectException(UniqueConstraintViolationException::class);
        $this->connection->insert('urssaf_paje_outbox', OutboxMessage::of($event)->row(), OutboxMessage::TYPES);
    }
}
