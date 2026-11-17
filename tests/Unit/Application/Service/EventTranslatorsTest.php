<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Service;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\DeclarationEventTranslator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\EmployerEventTranslator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\IntegrationEventFacts;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\IntegrationEventTranslator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\MandateEventTranslator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\DeclarationAlreadyDeclared;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\DeclarationDeclared;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\DeclarationOutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\DeclarationRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\EmployerForgotten;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\EmployerStatusChanged;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\MandateCancelled;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\MandateRegistered;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateOrigin;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\DomainEventSamples;

/**
 * What each domain event becomes in the host contract (ADR 0012): identifiers only, never an employer number.
 *
 * @mago-expect lint:too-many-methods
 */
final class EventTranslatorsTest extends TestCase
{
    private const AT = '2026-11-15T10:00:00+00:00';

    public function testEmployerRegisteredHasNoIntegrationEvent(): void
    {
        static::assertSame([], EmployerEventTranslator::translate(DomainEventSamples::employerRegistered()));
    }

    public function testAStageChangeBecomesAStatusChangeWithoutTheRawAnswer(): void
    {
        $event = self::single(EmployerEventTranslator::translate(DomainEventSamples::employerStageChanged()), EmployerStatusChanged::class);

        static::assertSame('urssaf.paje.employer.status_changed', $event->type());
        static::assertSame(1, $event->version());
        static::assertSame('2026-11-15T10:00:00Z', $event->occurredAt);
        static::assertSame('tenant-1', $event->tenantId);
        static::assertSame('account-1', $event->aggregateId);
        static::assertSame('employer-1', $event->externalReference);
        static::assertSame('tracked', $event->previousStage);
        static::assertSame('mandate_registered', $event->stage);
        static::assertNull($event->authorised);
        static::assertNull($event->onlineAccountState);
        static::assertSame(self::id($event, ['tracked', 'mandate_registered']), $event->eventId);
    }

    public function testARefreshBecomesAStatusChangeWithTheRawAnswerAndTheCurrentStage(): void
    {
        $event = self::single(EmployerEventTranslator::translate(DomainEventSamples::employerStatusRefreshed()), EmployerStatusChanged::class);

        static::assertSame('mandate_cancelled', $event->previousStage);
        static::assertSame('mandate_cancelled', $event->stage);
        static::assertTrue($event->authorised);
        static::assertSame('actif', $event->onlineAccountState);
        static::assertSame('account-1', $event->aggregateId);
        static::assertSame(self::id($event, ['mandate_cancelled', 'mandate_cancelled', '1', 'actif']), $event->eventId);
    }

    public function testForgottenCarriesTheErasedIdentifiersOnly(): void
    {
        $event = self::single(EmployerEventTranslator::translate(DomainEventSamples::employerForgotten()), EmployerForgotten::class);

        static::assertSame('urssaf.paje.employer.forgotten', $event->type());
        static::assertSame('account-1', $event->aggregateId);
        static::assertSame(['mandate-1', 'mandate-2'], $event->mandateIds);
        static::assertSame(['record-1', 'record-2'], $event->declarationRecordIds);
        static::assertSame(self::id($event, ['mandate-1', 'mandate-2', '|', 'record-1', 'record-2']), $event->eventId);
    }

    public function testMandateRegisteredCarriesTheAccountAndTheOrigin(): void
    {
        $registered = self::single(MandateEventTranslator::translate(DomainEventSamples::mandateRegistered()), MandateRegistered::class);
        $imported = self::single(MandateEventTranslator::translate(DomainEventSamples::mandateRegistered(MandateOrigin::Imported)), MandateRegistered::class);

        static::assertSame('urssaf.paje.mandate.registered', $registered->type());
        static::assertSame('mandate-1', $registered->aggregateId);
        static::assertSame('account-1', $registered->employerAccountId);
        static::assertSame('registered', $registered->origin);
        static::assertSame('imported', $imported->origin);
        static::assertSame(self::id($registered, ['registered']), $registered->eventId);
        static::assertSame(self::id($imported, ['imported']), $imported->eventId);
        static::assertNotSame($registered->eventId, $imported->eventId);
    }

    public function testMandateCancelledCarriesTheAccount(): void
    {
        $event = self::single(MandateEventTranslator::translate(DomainEventSamples::mandateCancelled()), MandateCancelled::class);

        static::assertSame('urssaf.paje.mandate.cancelled', $event->type());
        static::assertSame('account-1', $event->employerAccountId);
        static::assertSame('employer-1', $event->externalReference);
        static::assertSame(self::id($event, []), $event->eventId);
    }

    public function testDeclaredCarriesTheDocumentReferenceAndTheNonBlockingCodes(): void
    {
        $event = self::single(DeclarationEventTranslator::translate(DomainEventSamples::declarationDeclared()), DeclarationDeclared::class);

        static::assertSame('urssaf.paje.declaration.declared', $event->type());
        static::assertSame('record-1', $event->aggregateId);
        static::assertSame('declaration-1', $event->externalReference);
        static::assertSame('account-1', $event->employerAccountId);
        static::assertSame('home_care', $event->activityType);
        static::assertSame('2026-10-01', $event->periodStart);
        static::assertSame('2026-10-31', $event->periodEnd);
        static::assertSame('2026-123456', $event->referenceDocumentaire);
        static::assertSame(['AV_001', 'AV_002'], $event->nonBlockingCodes);
        static::assertSame(self::id($event, ['2026-123456', 'AV_001', 'AV_002']), $event->eventId);
    }

    public function testDeclaredWithoutReferenceAndForAnUntrackedEmployer(): void
    {
        $event = self::single(DeclarationEventTranslator::translate(DomainEventSamples::declarationDeclaredOfUntrackedEmployer(null)), DeclarationDeclared::class);

        static::assertNull($event->referenceDocumentaire);
        static::assertNull($event->employerAccountId);
        static::assertSame(self::id($event, ['', 'AV_001', 'AV_002']), $event->eventId);
    }

    public function testTheEventIdDependsOnTheReference(): void
    {
        $with = self::single(DeclarationEventTranslator::translate(DomainEventSamples::declarationDeclared('A')), DeclarationDeclared::class);
        $other = self::single(DeclarationEventTranslator::translate(DomainEventSamples::declarationDeclared('B')), DeclarationDeclared::class);
        $same = self::single(DeclarationEventTranslator::translate(DomainEventSamples::declarationDeclared('A')), DeclarationDeclared::class);

        static::assertNotSame($with->eventId, $other->eventId);
        static::assertSame($with->eventId, $same->eventId);
        static::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $with->eventId);
    }

    public function testRejectedCarriesTheErrorCodesOnly(): void
    {
        $event = self::single(DeclarationEventTranslator::translate(DomainEventSamples::declarationRejected()), DeclarationRejected::class);

        static::assertSame('urssaf.paje.declaration.rejected', $event->type());
        static::assertSame(['ER_API_050_0001', 'ER_API_050_0002'], $event->errorCodes);
        static::assertSame('childminder', $event->activityType);
        static::assertStringNotContainsString('message', print_r($event->toArray(), true));
        static::assertSame(self::id($event, ['ER_API_050_0001', 'ER_API_050_0002']), $event->eventId);
    }

    public function testAlreadyDeclaredCarriesTheCodesOfTheEvent(): void
    {
        $event = self::single(DeclarationEventTranslator::translate(DomainEventSamples::declarationAlreadyDeclared()), DeclarationAlreadyDeclared::class);

        static::assertSame('urssaf.paje.declaration.already_declared', $event->type());
        static::assertSame(['ER_API_050_0070'], $event->errorCodes);
        static::assertSame('account-1', $event->employerAccountId);
        static::assertSame(self::id($event, ['ER_API_050_0070']), $event->eventId);
    }

    public function testOutcomeUnknownCarriesTheReason(): void
    {
        $event = self::single(DeclarationEventTranslator::translate(DomainEventSamples::declarationOutcomeUnknown()), DeclarationOutcomeUnknown::class);

        static::assertSame('urssaf.paje.declaration.outcome_unknown', $event->type());
        static::assertSame('may_have_reached_urssaf', $event->reason);
        static::assertSame('home_care', $event->activityType);
        static::assertSame(self::id($event, ['may_have_reached_urssaf']), $event->eventId);
    }

    public function testTheEmployerAccountOfTheDomainEventIsCarriedOrNull(): void
    {
        $rejected = self::single(DeclarationEventTranslator::translate(DomainEventSamples::declarationRejected()), DeclarationRejected::class);
        $unknown = self::single(DeclarationEventTranslator::translate(DomainEventSamples::declarationOutcomeUnknown()), DeclarationOutcomeUnknown::class);

        static::assertSame('account-1', $rejected->employerAccountId);
        static::assertNull($unknown->employerAccountId);
    }

    public function testRejectedAndAlreadyDeclaredOfAnUntrackedEmployerHaveNoAccount(): void
    {
        $rejected = self::single(DeclarationEventTranslator::translate(DomainEventSamples::declarationRejectedOfUntrackedEmployer()), DeclarationRejected::class);
        $already = self::single(DeclarationEventTranslator::translate(DomainEventSamples::declarationAlreadyDeclaredOfUntrackedEmployer()), DeclarationAlreadyDeclared::class);

        static::assertNull($rejected->employerAccountId);
        static::assertNull($already->employerAccountId);
    }

    public function testTheOtherDeclarationEventsHaveNoIntegrationEvent(): void
    {
        foreach ([
            DomainEventSamples::declarationRecorded(),
            DomainEventSamples::declarationRequeued(),
            DomainEventSamples::declarationSubmissionFailed(),
            DomainEventSamples::declarationCredentialsRejected(),
        ] as $event) {
            static::assertSame([], IntegrationEventTranslator::translate($event));
        }
    }

    public function testTheTranslatorKeepsTheReleaseOrderAndSkipsWhatHasNoEvent(): void
    {
        $events = IntegrationEventTranslator::translate(
            DomainEventSamples::declarationRecorded(),
            DomainEventSamples::declarationDeclared(),
            DomainEventSamples::employerRegistered(),
            DomainEventSamples::mandateCancelled(),
            DomainEventSamples::employerForgotten(),
            DomainEventSamples::declarationRejected(),
        );

        static::assertSame(
            ['urssaf.paje.declaration.declared', 'urssaf.paje.mandate.cancelled', 'urssaf.paje.employer.forgotten', 'urssaf.paje.declaration.rejected'],
            array_map(static fn(IntegrationEvent $event): string => $event->type(), $events),
        );
        static::assertSame([], IntegrationEventTranslator::translate());
    }

    public function testNoIntegrationEventCarriesAnEmployerNumberOrABirthDate(): void
    {
        $events = IntegrationEventTranslator::translate(...self::allDomainEvents());

        static::assertCount(9, $events);
        foreach ($events as $event) {
            $dump = print_r($event->toArray(), true);
            static::assertStringNotContainsString('Y1869005780009', $dump);
            static::assertStringNotContainsString('1975-02-23', $dump);
            static::assertSame(self::AT, (new DateTimeImmutable($event->occurredAt))->format('c'));
        }
    }

    /**
     * @param list<?string> $facts
     */
    private static function id(IntegrationEvent $event, array $facts): string
    {
        return IntegrationEventFacts::id($event->type(), $event->aggregateId, new DateTimeImmutable($event->occurredAt), $facts);
    }

    /**
     * @return list<DomainEvent>
     */
    private static function allDomainEvents(): array
    {
        return [
            DomainEventSamples::employerRegistered(),
            DomainEventSamples::employerStatusRefreshed(),
            DomainEventSamples::employerStageChanged(),
            DomainEventSamples::employerForgotten(),
            DomainEventSamples::mandateRegistered(),
            DomainEventSamples::mandateCancelled(),
            DomainEventSamples::declarationDeclared(),
            DomainEventSamples::declarationRejected(),
            DomainEventSamples::declarationAlreadyDeclared(),
            DomainEventSamples::declarationOutcomeUnknown(),
            DomainEventSamples::declarationRecorded(),
        ];
    }

    /**
     * @template T of IntegrationEvent
     *
     * @param list<IntegrationEvent> $events
     * @param class-string<T>        $class
     *
     * @return T
     */
    private static function single(array $events, string $class): IntegrationEvent
    {
        static::assertCount(1, $events);
        static::assertInstanceOf($class, $events[0]);

        return $events[0];
    }
}
