<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employer;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\OnlineAccountState;

final class EmployerVerificationTest extends TestCase
{
    public function testOfExposesTheFields(): void
    {
        $state = OnlineAccountState::of('Actif');

        $verification = EmployerVerification::of(true, $state);

        static::assertTrue($verification->authorised());
        static::assertSame($state, $verification->state());
        static::assertFalse(EmployerVerification::of(false, $state)->authorised());
    }
}
