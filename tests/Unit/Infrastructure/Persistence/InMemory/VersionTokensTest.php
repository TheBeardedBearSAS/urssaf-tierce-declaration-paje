<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Persistence\InMemory;

use PHPUnit\Framework\TestCase;
use stdClass;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\VersionTokens;

final class VersionTokensTest extends TestCase
{
    private VersionTokens $versions;

    protected function setUp(): void
    {
        $this->versions = new VersionTokens('thing.concurrent_modification', 'Changed.');
    }

    public function testANewInstanceCanBeSavedOnceAndThenAgain(): void
    {
        $thing = new stdClass();

        static::assertTrue($this->isCurrent($thing, 'a'));
        $this->versions->saved($thing, 'a');
        static::assertTrue($this->isCurrent($thing, 'a'));
        $this->versions->saved($thing, 'a');
        static::assertTrue($this->isCurrent($thing, 'a'));

    }

    public function testAnotherSaveMakesTheOtherInstancesStale(): void
    {
        $original = new stdClass();
        $this->versions->saved($original, 'a');
        $first = $this->versions->track(clone $original, 'a');
        $second = $this->versions->track(clone $original, 'a');
        $this->versions->saved($first, 'a');

        static::assertTrue($this->isCurrent($first, 'a'));
        $this->assertStale($second, 'a');
        $this->assertStale($original, 'a');
    }

    public function testAnInstanceNeverSavedIsStaleForAStoredIdentifier(): void
    {
        $this->versions->saved(new stdClass(), 'a');

        $this->assertStale(new stdClass(), 'a');
        static::assertTrue($this->isCurrent(new stdClass(), 'b'));
    }

    public function testABumpMakesTheReadInstancesStale(): void
    {
        $this->versions->saved(new stdClass(), 'a');
        $read = $this->versions->track(new stdClass(), 'a');
        $this->versions->bump('a');

        $this->assertStale($read, 'a');
        static::assertTrue($this->isCurrent($this->versions->track(new stdClass(), 'a'), 'a'));
    }

    public function testAnErasedIdentifierIsStaleForTheInstancesReadBeforeAndFreeAfter(): void
    {
        $this->versions->saved(new stdClass(), 'a');
        $read = $this->versions->track(new stdClass(), 'a');
        $this->versions->forget('a');

        $this->assertStale($read, 'a');
        static::assertTrue($this->isCurrent(new stdClass(), 'a'));
    }

    public function testTheViolationCarriesTheCodeAndTheMessage(): void
    {
        $this->versions->saved(new stdClass(), 'a');

        try {
            $this->versions->assertCurrent(new stdClass(), 'a');
            static::fail('Expected a violation.');
        } catch (InvariantViolation $violation) {
            static::assertSame('thing.concurrent_modification', $violation->violationCode());
            static::assertSame('Changed.', $violation->getMessage());
            static::assertSame([], $violation->params());
        }
    }

    private function isCurrent(object $instance, string $id): bool
    {
        try {
            $this->versions->assertCurrent($instance, $id);
        } catch (InvariantViolation) {
            return false;
        }

        return true;
    }

    private function assertStale(object $instance, string $id): void
    {
        try {
            $this->versions->assertCurrent($instance, $id);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $violation) {
            static::assertSame('thing.concurrent_modification', $violation->violationCode());
        }
    }
}
