<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support;

use Closure;
use PHPUnit\Framework\Assert;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\Payload;

final class WireAssertions
{
    private function __construct() {}

    /**
     * Asserts the value is not null and returns it, narrowed.
     *
     * @template T of object
     *
     * @param T|null $value
     *
     * @return T
     */
    public static function present(?object $value): object
    {
        Assert::assertNotNull($value);

        return $value;
    }

    /**
     * Runs the action and asserts it throws an UnexpectedUrssafResponse with exactly this message.
     *
     * @param Closure(): mixed $action
     *
     * @param-immediately-invoked-callable $action
     */
    public static function assertUnexpected(string $expectedMessage, Closure $action): void
    {
        Assert::assertSame($expectedMessage, self::caught($action)->getMessage());
    }

    /**
     * Asserts that a response mapper refuses every 2xx body that is not a JSON object: the empty body, `[]` and the
     * top-level lists. Such a body must never read as a success whose fields are all absent (ADR 0002, ADR 0003).
     *
     * @param Closure(Payload): mixed $map
     *
     * @param-immediately-invoked-callable $map
     */
    public static function assertRefusesWhatIsNotAnObject(Closure $map): void
    {
        foreach (['', "\n", '[]', '[{"x":1}]', '[1,2]', '[[]]'] as $body) {
            self::assertUnexpected(
                'Unexpected URSSAF response: body is not a JSON object.',
                static fn(): mixed => $map(JsonDecoder::decode($body)),
            );
        }
        self::assertUnexpected(
            'Unexpected URSSAF response: body is not a JSON object.',
            static fn(): mixed => $map(Payload::empty()),
        );
    }

    /**
     * Runs the action and returns the UnexpectedUrssafResponse it throws.
     *
     * @param Closure(): mixed $action
     *
     * @param-immediately-invoked-callable $action
     */
    public static function caught(Closure $action): UnexpectedUrssafResponse
    {
        try {
            $action();
        } catch (UnexpectedUrssafResponse $exception) {
            return $exception;
        }
        Assert::fail('Expected an UnexpectedUrssafResponse.');
    }
}
