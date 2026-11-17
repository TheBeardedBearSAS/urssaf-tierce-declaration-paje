<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class DeclarationStateTest extends TestCase
{
    /**
     * @return iterable<string, array{DeclarationState, list<DeclarationState>}>
     */
    public static function transitions(): iterable
    {
        yield 'pending' => [DeclarationState::Pending, [DeclarationState::Submitting]];
        yield 'submitting' => [DeclarationState::Submitting, [
            DeclarationState::Declared,
            DeclarationState::Rejected,
            DeclarationState::AlreadyDeclared,
            DeclarationState::OutcomeUnknown,
            DeclarationState::Error,
            DeclarationState::InvalidCredentials,
        ]];
        yield 'outcome_unknown' => [DeclarationState::OutcomeUnknown, [DeclarationState::Declared, DeclarationState::Pending]];
        yield 'error' => [DeclarationState::Error, [DeclarationState::Pending]];
        yield 'invalid_credentials' => [DeclarationState::InvalidCredentials, [DeclarationState::Pending]];
        yield 'declared' => [DeclarationState::Declared, []];
        yield 'rejected' => [DeclarationState::Rejected, []];
        yield 'already_declared' => [DeclarationState::AlreadyDeclared, []];
    }

    /**
     * @param list<DeclarationState> $allowed
     */
    #[DataProvider('transitions')]
    public function testEveryTransitionOfTheAdr(DeclarationState $from, array $allowed): void
    {
        static::assertSame($allowed, $from->allowedTransitions());
        static::assertSame($allowed === [], $from->isFinal());
        foreach (DeclarationState::cases() as $target) {
            $expected = \in_array($target, $allowed, true);
            static::assertSame($expected, $from->canTransitionTo($target));
            if ($expected) {
                static::assertSame($target, $from->transitionTo($target));

                continue;
            }
            $exception = DomainAssertions::assertViolationCode(
                'declaration_record.invalid_state_transition',
                static fn(): DeclarationState => $from->transitionTo($target),
            );
            static::assertInstanceOf(InvalidStateTransition::class, $exception);
            static::assertSame('declaration_record', $exception->aggregate());
            static::assertSame($from->value, $exception->from());
            static::assertSame($target->value, $exception->to());
        }
    }

    public function testBackedValues(): void
    {
        static::assertSame(
            ['pending', 'submitting', 'declared', 'rejected', 'already_declared', 'outcome_unknown', 'error', 'invalid_credentials'],
            array_map(static fn(DeclarationState $state): string => $state->value, DeclarationState::cases()),
        );
    }
}
