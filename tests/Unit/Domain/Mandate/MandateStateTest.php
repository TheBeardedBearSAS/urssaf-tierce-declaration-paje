<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Mandate;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateOrigin;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateState;

final class MandateStateTest extends TestCase
{
    public function testValues(): void
    {
        static::assertSame(['registered', 'cancelled'], array_map(static fn(MandateState $state): string => $state->value, MandateState::cases()));
        static::assertSame(['registered', 'imported'], array_map(static fn(MandateOrigin $origin): string => $origin->value, MandateOrigin::cases()));
    }

    public function testAllowedTransitions(): void
    {
        static::assertSame([MandateState::Cancelled], MandateState::Registered->allowedTransitions());
        static::assertSame([], MandateState::Cancelled->allowedTransitions());
    }

    public function testCanTransitionTo(): void
    {
        static::assertTrue(MandateState::Registered->canTransitionTo(MandateState::Cancelled));
        static::assertFalse(MandateState::Registered->canTransitionTo(MandateState::Registered));
        static::assertFalse(MandateState::Cancelled->canTransitionTo(MandateState::Registered));
        static::assertFalse(MandateState::Cancelled->canTransitionTo(MandateState::Cancelled));
    }

    public function testTransitionTo(): void
    {
        static::assertSame(MandateState::Cancelled, MandateState::Registered->transitionTo(MandateState::Cancelled));
    }

    public function testRefusedTransition(): void
    {
        try {
            MandateState::Cancelled->transitionTo(MandateState::Registered);
            static::fail('Expected an InvalidStateTransition.');
        } catch (InvalidStateTransition $exception) {
            static::assertSame('mandate.invalid_state_transition', $exception->violationCode());
            static::assertSame('mandate', $exception->aggregate());
            static::assertSame('cancelled', $exception->from());
            static::assertSame('registered', $exception->to());
        }
    }

    public function testActiveAndFinal(): void
    {
        static::assertTrue(MandateState::Registered->isActive());
        static::assertFalse(MandateState::Registered->isFinal());
        static::assertFalse(MandateState::Cancelled->isActive());
        static::assertTrue(MandateState::Cancelled->isFinal());
    }
}
