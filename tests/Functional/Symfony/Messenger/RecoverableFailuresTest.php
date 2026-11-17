<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Messenger;

use LogicException;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\MessageHandler\RecoverableFailures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\RetryDelay;

final class RecoverableFailuresTest extends TestCase
{
    public function testTheCapabilityIsDetectedFromTheInstalledMessengerVersion(): void
    {
        static::assertSame(RetryDelay::isSupported(), RecoverableFailures::detect()->supportsRetryDelay());
    }

    public function testTheRetryDelayIsDroppedWhenMessengerCannotCarryIt(): void
    {
        $previous = new LogicException('busy');

        $failure = RecoverableFailures::withRetryDelaySupport(false)->create('later', $previous, 5000);

        static::assertFalse(RecoverableFailures::withRetryDelaySupport(false)->supportsRetryDelay());
        RetryDelay::assertCarries(null, $failure);
        static::assertSame('later', $failure->getMessage());
        static::assertSame(0, $failure->getCode());
        static::assertSame($previous, $failure->getPrevious());
    }

    public function testTheRetryDelayIsPassedWhenMessengerCanCarryIt(): void
    {
        if (!RetryDelay::isSupported()) {
            static::markTestSkipped('The installed symfony/messenger has no retry delay.');
        }
        $previous = new LogicException('busy');

        $failure = RecoverableFailures::withRetryDelaySupport(true)->create('later', $previous, 5000);

        static::assertTrue(RecoverableFailures::withRetryDelaySupport(true)->supportsRetryDelay());
        static::assertSame(5000, $failure->getRetryDelay());
        static::assertSame($previous, $failure->getPrevious());
        static::assertSame(0, $failure->getCode());
    }
}
