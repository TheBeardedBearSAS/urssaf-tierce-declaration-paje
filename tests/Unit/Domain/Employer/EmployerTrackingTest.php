<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employer;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerTracking;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerVerification;

final class EmployerTrackingTest extends TestCase
{
    public function testTracked(): void
    {
        $at = EmployerSamples::instant();

        $tracking = EmployerTracking::tracked($at);

        static::assertSame(EmployerStage::Tracked, $tracking->stage());
        static::assertNull($tracking->lastVerification());
        static::assertNull($tracking->lastCheckedAt());
        static::assertSame($at, $tracking->updatedAt());
        static::assertFalse($tracking->hasVerification(EmployerSamples::verification()));
    }

    public function testWithStageKeepsTheSnapshot(): void
    {
        $verified = EmployerTracking::tracked(EmployerSamples::instant())->withVerification(EmployerSamples::verification(), EmployerSamples::instant('2026-11-16T10:00:00Z'));
        $later = EmployerSamples::instant('2026-11-17T10:00:00Z');

        $tracking = $verified->withStage(EmployerStage::MandateRegistered, $later);

        static::assertSame(EmployerStage::MandateRegistered, $tracking->stage());
        static::assertSame($verified->lastVerification(), $tracking->lastVerification());
        static::assertSame($verified->lastCheckedAt(), $tracking->lastCheckedAt());
        static::assertSame($later, $tracking->updatedAt());
    }

    public function testWithVerificationKeepsTheStage(): void
    {
        $now = EmployerSamples::instant('2026-11-16T10:00:00Z');
        $verification = EmployerSamples::verification();

        $tracking = EmployerTracking::tracked(EmployerSamples::instant())->withStage(EmployerStage::MandateCancelled, EmployerSamples::instant())->withVerification($verification, $now);

        static::assertSame(EmployerStage::MandateCancelled, $tracking->stage());
        static::assertSame($verification, $tracking->lastVerification());
        static::assertSame($now, $tracking->lastCheckedAt());
        static::assertSame($now, $tracking->updatedAt());
    }

    public function testHasVerificationComparesTheAuthorisationAndTheRawState(): void
    {
        $tracking = EmployerTracking::tracked(EmployerSamples::instant())->withVerification(EmployerSamples::verification(true, 'Actif'), EmployerSamples::instant());

        static::assertTrue($tracking->hasVerification(EmployerSamples::verification(true, 'Actif')));
        static::assertFalse($tracking->hasVerification(EmployerSamples::verification(false, 'Actif')));
        static::assertFalse($tracking->hasVerification(EmployerSamples::verification(true, 'Absent')));
    }

    public function testEquality(): void
    {
        $at = EmployerSamples::instant();
        $verified = static fn(EmployerVerification $verification, string $checked): EmployerTracking => EmployerTracking::tracked($at)->withVerification($verification, EmployerSamples::instant($checked));
        $authorised = EmployerSamples::verification();
        $refused = EmployerSamples::verification(false);

        static::assertTrue(EmployerTracking::tracked($at)->equals(EmployerTracking::tracked($at)));
        static::assertTrue($verified($authorised, '2026-11-16T10:00:00Z')->equals($verified($authorised, '2026-11-16T10:00:00Z')));
        static::assertFalse($verified($authorised, '2026-11-16T10:00:00Z')->equals($verified($refused, '2026-11-16T10:00:00Z')));
        static::assertFalse($verified($authorised, '2026-11-16T10:00:00Z')->equals(EmployerTracking::tracked($at)));
        static::assertFalse(EmployerTracking::tracked($at)->equals($verified($authorised, '2026-11-16T10:00:00Z')));
        static::assertFalse(EmployerTracking::tracked($at)->equals(EmployerTracking::tracked($at)->withStage(EmployerStage::MandateRegistered, $at)));
        static::assertFalse(EmployerTracking::tracked($at)->equals(EmployerTracking::tracked(EmployerSamples::instant('2026-11-16T10:00:00Z'))));
        static::assertFalse($verified($authorised, '2026-11-16T10:00:00Z')->equals($verified($authorised, '2026-11-17T10:00:00Z')));
    }
}
