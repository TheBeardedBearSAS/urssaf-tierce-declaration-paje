<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Exception;

use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RetryableException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

final class UnexpectedUrssafResponseTest extends TestCase
{
    public function testInvalidPayloadCarriesTheReason(): void
    {
        $exception = UnexpectedUrssafResponse::invalidPayload('field "x" is missing or empty');

        static::assertSame('Unexpected URSSAF response: field "x" is missing or empty.', $exception->getMessage());
        static::assertSame(0, $exception->getCode());
        static::assertNull($exception->getPrevious());
        static::assertTrue((new ReflectionClass($exception))->implementsInterface(UrssafException::class));
    }

    public function testCausedByKeepsThePreviousException(): void
    {
        $previous = new LogicException('inner');

        $exception = UnexpectedUrssafResponse::causedBy('body is not valid JSON', $previous);

        static::assertSame('Unexpected URSSAF response: body is not valid JSON.', $exception->getMessage());
        static::assertSame(0, $exception->getCode());
        static::assertSame($previous, $exception->getPrevious());
    }

    public function testUnexpectedStatusCarriesTheStatusAsCode(): void
    {
        $exception = UnexpectedUrssafResponse::unexpectedStatus(418);

        static::assertSame('Unexpected URSSAF response: HTTP 418.', $exception->getMessage());
        static::assertSame(418, $exception->getCode());
    }

    public function testItIsNotRetryable(): void
    {
        static::assertFalse((new ReflectionClass(UnexpectedUrssafResponse::class))->implementsInterface(RetryableException::class));
    }
}
