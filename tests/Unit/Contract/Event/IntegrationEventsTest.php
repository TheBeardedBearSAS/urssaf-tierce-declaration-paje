<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Contract\Event;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\DeclarationAlreadyDeclared;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\DeclarationDeclared;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\DeclarationOutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\DeclarationRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\EmployerForgotten;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\EmployerStatusChanged;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\IntegrationEventCodec;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\MandateCancelled;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\MandateRegistered;

final class IntegrationEventsTest extends TestCase
{
    private const COMMON = [
        'eventId' => 'event-1',
        'occurredAt' => '2026-11-15T10:00:00Z',
        'tenantId' => 'tenant-1',
        'aggregateId' => 'aggregate-1',
        'externalReference' => 'reference-1',
    ];

    /**
     * @param array<string, mixed> $specific
     */
    #[DataProvider('provideEvents')]
    public function testAnEventExposesItsTypeVersionAndProperties(IntegrationEvent $event, string $type, array $specific): void
    {
        static::assertSame($type, $event->type());
        static::assertSame($event::TYPE, $type);
        static::assertSame(1, $event->version());
        static::assertSame([...self::COMMON, ...$specific], $event->toArray());
    }

    /**
     * @return iterable<string, array{IntegrationEvent, string, array<string, mixed>}>
     */
    public static function provideEvents(): iterable
    {
        $declaration = [
            'employerAccountId' => 'employer-1',
            'activityType' => 'childminder',
            'periodStart' => '2026-10-01',
            'periodEnd' => '2026-10-31',
        ];
        yield 'employer status changed' => [
            new EmployerStatusChanged('event-1', '2026-11-15T10:00:00Z', 'tenant-1', 'aggregate-1', 'reference-1', 'tracked', 'mandate_registered', true, 'actif'),
            'urssaf.paje.employer.status_changed',
            ['previousStage' => 'tracked', 'stage' => 'mandate_registered', 'authorised' => true, 'onlineAccountState' => 'actif'],
        ];
        yield 'employer forgotten' => [
            new EmployerForgotten('event-1', '2026-11-15T10:00:00Z', 'tenant-1', 'aggregate-1', 'reference-1', ['mandate-1', 'mandate-2'], ['record-1']),
            'urssaf.paje.employer.forgotten',
            ['mandateIds' => ['mandate-1', 'mandate-2'], 'declarationRecordIds' => ['record-1']],
        ];
        yield 'mandate registered' => [
            new MandateRegistered('event-1', '2026-11-15T10:00:00Z', 'tenant-1', 'aggregate-1', 'reference-1', 'employer-1', 'imported'),
            'urssaf.paje.mandate.registered',
            ['employerAccountId' => 'employer-1', 'origin' => 'imported'],
        ];
        yield 'mandate cancelled' => [
            new MandateCancelled('event-1', '2026-11-15T10:00:00Z', 'tenant-1', 'aggregate-1', 'reference-1', 'employer-1'),
            'urssaf.paje.mandate.cancelled',
            ['employerAccountId' => 'employer-1'],
        ];
        yield 'declaration declared' => [
            new DeclarationDeclared('event-1', '2026-11-15T10:00:00Z', 'tenant-1', 'aggregate-1', 'reference-1', 'employer-1', 'childminder', '2026-10-01', '2026-10-31', 'DOC-1', ['W_1']),
            'urssaf.paje.declaration.declared',
            [...$declaration, 'referenceDocumentaire' => 'DOC-1', 'nonBlockingCodes' => ['W_1']],
        ];
        yield 'declaration rejected' => [
            new DeclarationRejected('event-1', '2026-11-15T10:00:00Z', 'tenant-1', 'aggregate-1', 'reference-1', 'employer-1', 'childminder', '2026-10-01', '2026-10-31', ['ERR_A', 'ERR_B']),
            'urssaf.paje.declaration.rejected',
            [...$declaration, 'errorCodes' => ['ERR_A', 'ERR_B']],
        ];
        yield 'declaration outcome unknown' => [
            new DeclarationOutcomeUnknown('event-1', '2026-11-15T10:00:00Z', 'tenant-1', 'aggregate-1', 'reference-1', 'employer-1', 'childminder', '2026-10-01', '2026-10-31', 'stale_submission'),
            'urssaf.paje.declaration.outcome_unknown',
            [...$declaration, 'reason' => 'stale_submission'],
        ];
        yield 'declaration already declared' => [
            new DeclarationAlreadyDeclared('event-1', '2026-11-15T10:00:00Z', 'tenant-1', 'aggregate-1', 'reference-1', 'employer-1', 'childminder', '2026-10-01', '2026-10-31', ['ER_API_050_0070']),
            'urssaf.paje.declaration.already_declared',
            [...$declaration, 'errorCodes' => ['ER_API_050_0070']],
        ];
    }

    public function testEveryTypeIsUniqueAndTheCatalogueIsComplete(): void
    {
        $types = [];
        $classes = [];
        foreach (self::provideEvents() as [$event]) {
            $types[] = $event->type();
            $classes[] = $event::class;
        }

        static::assertCount(8, array_unique($types));
        static::assertEqualsCanonicalizing(IntegrationEventCodec::CLASSES, $classes);
    }

    public function testAnUntrackedEmployerHasNoAccountId(): void
    {
        $event = new DeclarationRejected('e', '2026-11-15T10:00:00Z', 't', 'a', 'r', null, 'home_care', '2026-10-01', '2026-10-31', []);

        static::assertNull($event->employerAccountId);
        static::assertSame('home_care', $event->activityType);
    }
}
