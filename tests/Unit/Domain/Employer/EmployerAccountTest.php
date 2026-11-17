<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employer;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerOrigin;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\Event\EmployerForgotten;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\Event\EmployerRegistered;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\Event\EmployerStageChanged;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\Event\EmployerStatusRefreshed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\DomainEvent;

/**
 * One method per command and scenario of the aggregate.
 *
 * @mago-expect lint:too-many-methods
 */
final class EmployerAccountTest extends TestCase
{
    private static function track(): EmployerAccount
    {
        return EmployerAccount::track(
            EmployerSamples::accountId(),
            EmployerSamples::tenantId(),
            EmployerSamples::externalReference(),
            EmployerSamples::employer(),
            EmployerSamples::instant(),
        );
    }

    public function testTrack(): void
    {
        $account = self::track();

        static::assertSame('account-1', $account->id()->value());
        static::assertSame('tenant-1', $account->tenantId()->value());
        static::assertSame('employer-ref-1', $account->externalReference()->value());
        static::assertTrue($account->employer()->equals(EmployerSamples::employer()));
        static::assertSame('Y1869005780009', $account->employerNumber()->value());
        static::assertSame('1975-02-23', $account->birthDate()->toString());
        static::assertSame(EmployerOrigin::Tracked, $account->origin());
        static::assertSame('2026-11-15T10:00:00Z', $account->createdAt()->toIso8601());
        static::assertSame(EmployerStage::Tracked, $account->stage());
        static::assertNull($account->lastVerification());
        static::assertNull($account->lastCheckedAt());
        static::assertSame('2026-11-15T10:00:00Z', $account->updatedAt()->toIso8601());
        static::assertSame(EmployerStage::Tracked, $account->tracking()->stage());

        $events = $account->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(EmployerRegistered::class, $event);
        static::assertSame('account-1', $event->accountId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame('employer-ref-1', $event->externalReference()->value());
        static::assertSame(EmployerOrigin::Tracked, $event->origin());
        static::assertSame('2026-11-15T10:00:00+00:00', $event->occurredAt()->format('c'));
        static::assertSame([], $account->releaseEvents());
    }

    public function testImport(): void
    {
        $account = EmployerAccount::import(
            EmployerSamples::accountId(),
            EmployerSamples::tenantId(),
            EmployerSamples::externalReference(),
            EmployerSamples::employer(),
            EmployerSamples::instant(),
        );

        static::assertSame(EmployerOrigin::Imported, $account->origin());
        static::assertSame(EmployerStage::Tracked, $account->stage());
        $events = $account->releaseEvents();
        static::assertCount(1, $events);
        static::assertInstanceOf(EmployerRegistered::class, $events[0]);
        static::assertSame(EmployerOrigin::Imported, $events[0]->origin());
    }

    public function testMandateRegistered(): void
    {
        $account = EmployerSamples::account();
        $now = EmployerSamples::instant('2026-11-16T10:00:00Z');

        $account->markMandateRegistered($now);

        static::assertSame(EmployerStage::MandateRegistered, $account->stage());
        static::assertSame($now, $account->updatedAt());
        $events = $account->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(EmployerStageChanged::class, $event);
        static::assertSame('account-1', $event->accountId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame('employer-ref-1', $event->externalReference()->value());
        static::assertSame(EmployerStage::Tracked, $event->from());
        static::assertSame(EmployerStage::MandateRegistered, $event->to());
        static::assertSame('2026-11-16T10:00:00+00:00', $event->occurredAt()->format('c'));
        static::assertSame([], $account->releaseEvents());
    }

    public function testRepeatedMandateRegistrationOnlyTouchesTheAccount(): void
    {
        $account = EmployerSamples::account();
        $account->markMandateRegistered(EmployerSamples::instant('2026-11-16T10:00:00Z'));
        $account->releaseEvents();
        $later = EmployerSamples::instant('2026-11-17T10:00:00Z');

        $account->markMandateRegistered($later);

        static::assertSame(EmployerStage::MandateRegistered, $account->stage());
        static::assertSame($later, $account->updatedAt());
        static::assertSame([], $account->releaseEvents());
    }

    public function testMandateCancelled(): void
    {
        $account = EmployerSamples::account();
        $account->markMandateRegistered(EmployerSamples::instant('2026-11-16T10:00:00Z'));
        $account->releaseEvents();
        $now = EmployerSamples::instant('2026-11-17T10:00:00Z');

        $account->markMandateCancelled($now);

        static::assertSame(EmployerStage::MandateCancelled, $account->stage());
        static::assertSame($now, $account->updatedAt());
        $events = $account->releaseEvents();
        static::assertCount(1, $events);
        static::assertInstanceOf(EmployerStageChanged::class, $events[0]);
        static::assertSame(EmployerStage::MandateRegistered, $events[0]->from());
        static::assertSame(EmployerStage::MandateCancelled, $events[0]->to());

        $account->markMandateCancelled(EmployerSamples::instant('2026-11-18T10:00:00Z'));
        static::assertSame([], $account->releaseEvents());
    }

    public function testRegistrationAfterCancellation(): void
    {
        $account = EmployerSamples::account();
        $account->markMandateCancelled(EmployerSamples::instant('2026-11-16T10:00:00Z'));
        $account->releaseEvents();

        $account->markMandateRegistered(EmployerSamples::instant('2026-11-17T10:00:00Z'));

        static::assertSame(EmployerStage::MandateRegistered, $account->stage());
        static::assertCount(1, $account->releaseEvents());
    }

    public function testFirstVerificationRecordsAnEventAndKeepsTheStage(): void
    {
        $account = EmployerSamples::account();
        $verification = EmployerSamples::verification();
        $now = EmployerSamples::instant('2026-11-16T10:00:00Z');

        $account->applyVerification($verification, $now);

        static::assertSame(EmployerStage::Tracked, $account->stage());
        static::assertSame($verification, $account->lastVerification());
        static::assertSame($now, $account->lastCheckedAt());
        static::assertSame($now, $account->updatedAt());
        $events = $account->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(EmployerStatusRefreshed::class, $event);
        static::assertSame('account-1', $event->accountId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame('employer-ref-1', $event->externalReference()->value());
        static::assertSame($verification, $event->verification());
        static::assertSame('2026-11-16T10:00:00+00:00', $event->occurredAt()->format('c'));
        static::assertSame([], $account->releaseEvents());
    }

    public function testAnUnchangedVerificationOnlyRefreshesTheCheckInstants(): void
    {
        $account = EmployerSamples::account();
        $account->applyVerification(EmployerSamples::verification(), EmployerSamples::instant('2026-11-16T10:00:00Z'));
        $account->releaseEvents();
        $later = EmployerSamples::instant('2026-11-17T10:00:00Z');

        $account->applyVerification(EmployerSamples::verification(), $later);

        static::assertSame([], $account->releaseEvents());
        static::assertSame($later, $account->lastCheckedAt());
        static::assertSame($later, $account->updatedAt());
    }

    public function testADifferentVerificationRecordsAnEvent(): void
    {
        $account = EmployerSamples::account();
        $account->applyVerification(EmployerSamples::verification(), EmployerSamples::instant('2026-11-16T10:00:00Z'));
        $account->releaseEvents();

        $account->applyVerification(EmployerSamples::verification(false, 'Absent'), EmployerSamples::instant('2026-11-17T10:00:00Z'));

        $events = $account->releaseEvents();
        static::assertCount(1, $events);
        static::assertInstanceOf(EmployerStatusRefreshed::class, $events[0]);
    }

    public function testAVerificationNeverChangesTheStage(): void
    {
        foreach ([EmployerStage::MandateRegistered, EmployerStage::MandateCancelled] as $stage) {
            $account = EmployerSamples::account();
            $stage === EmployerStage::MandateRegistered
                ? $account->markMandateRegistered(EmployerSamples::instant('2026-11-16T10:00:00Z'))
                : $account->markMandateCancelled(EmployerSamples::instant('2026-11-16T10:00:00Z'));

            $account->applyVerification(EmployerSamples::verification(false, 'Absent'), EmployerSamples::instant('2026-11-17T10:00:00Z'));

            static::assertSame($stage, $account->stage(), $stage->value);
        }
    }

    public function testEventsAreReleasedInOrder(): void
    {
        $account = self::track();
        $account->markMandateRegistered(EmployerSamples::instant('2026-11-16T10:00:00Z'));
        $account->applyVerification(EmployerSamples::verification(), EmployerSamples::instant('2026-11-17T10:00:00Z'));

        static::assertSame(
            [EmployerRegistered::class, EmployerStageChanged::class, EmployerStatusRefreshed::class],
            array_map(static fn(DomainEvent $event): string => $event::class, $account->releaseEvents()),
        );
    }

    public function testForgettingRecordsOneEventWithTheErasedIdsAndKeepsTheState(): void
    {
        $account = EmployerSamples::account();
        $account->markMandateRegistered(EmployerSamples::instant('2026-11-16T10:00:00Z'));
        $account->releaseEvents();
        $updatedAt = $account->updatedAt();
        $mandateIds = [MandateId::fromString('mandate-1')];
        $recordIds = [DeclarationRecordId::fromString('record-1'), DeclarationRecordId::fromString('record-2')];

        $account->forget(EmployerSamples::instant('2026-11-18T10:00:00Z'), $mandateIds, $recordIds);

        $events = $account->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(EmployerForgotten::class, $event);
        static::assertSame($account->id(), $event->accountId());
        static::assertSame($account->tenantId(), $event->tenantId());
        static::assertSame($account->externalReference(), $event->externalReference());
        static::assertSame($mandateIds, $event->mandateIds());
        static::assertSame($recordIds, $event->declarationRecordIds());
        static::assertSame('2026-11-18T10:00:00+00:00', $event->occurredAt()->format('c'));
        static::assertSame(EmployerStage::MandateRegistered, $account->stage());
        static::assertSame($updatedAt, $account->updatedAt());
        static::assertSame([], $account->releaseEvents());
    }

    public function testNoEventCarriesAnEmployerNumberOrABirthDate(): void
    {
        $account = self::track();
        $account->markMandateRegistered(EmployerSamples::instant('2026-11-16T10:00:00Z'));
        $account->applyVerification(EmployerSamples::verification(), EmployerSamples::instant('2026-11-17T10:00:00Z'));
        $account->forget(EmployerSamples::instant('2026-11-18T10:00:00Z'), [], []);

        foreach ($account->releaseEvents() as $event) {
            $dump = print_r($event, true);
            static::assertStringNotContainsString('Y1869005780009', $dump, $event::class);
            static::assertStringNotContainsString('1975', $dump, $event::class);
        }
    }
}
