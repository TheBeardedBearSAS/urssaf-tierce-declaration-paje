<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain;

use Closure;
use PHPUnit\Framework\Assert;
use ReflectionMethod;
use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\DomainException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\Violation;

/**
 * Assertion helpers shared by every domain test.
 */
final class DomainAssertions
{
    /**
     * Runs the action and asserts it throws a domain exception with the given violation code.
     *
     * @param Closure(): mixed $action
     *
     * @param-immediately-invoked-callable $action
     */
    public static function assertViolationCode(string $expectedCode, Closure $action): DomainException
    {
        try {
            $action();
        } catch (DomainException $exception) {
            Assert::assertSame($expectedCode, $exception->violationCode());

            return $exception;
        }
        Assert::fail(\sprintf('Expected a domain exception with code "%s".', $expectedCode));
    }

    /**
     * Asserts that exactly the named parameters of the method carry `#[SensitiveParameter]` (ADR 0007): a stack trace
     * then redacts them instead of printing a birth date or a number in clear.
     *
     * @param class-string $class
     * @param list<string> $expected parameter names, in declaration order
     */
    public static function assertSensitiveParameters(string $class, string $method, array $expected): void
    {
        $sensitive = [];
        foreach ((new ReflectionMethod($class, $method))->getParameters() as $parameter) {
            if ($parameter->getAttributes(SensitiveParameter::class) === []) {
                continue;
            }

            $sensitive[] = $parameter->getName();
        }
        Assert::assertSame($expected, $sensitive);
    }

    /**
     * Compact representation of a validation result for assertions: "<severity> <path> <code>", in order.
     *
     * @return list<string>
     */
    public static function summary(ValidationResult $result): array
    {
        return array_map(
            static fn(Violation $violation): string => \sprintf(
                '%s %s %s',
                $violation->severity()->value,
                $violation->path(),
                $violation->code(),
            ),
            $result->all(),
        );
    }
}
