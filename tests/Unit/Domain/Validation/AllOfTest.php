<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Validation;

use PHPUnit\Framework\TestCase;
use stdClass;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\AllOf;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\Validator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\Violation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class AllOfTest extends TestCase
{
    public function testWithoutValidatorsTheResultIsValid(): void
    {
        /** @var list<Validator<stdClass>> $validators */
        $validators = [];

        $result = AllOf::of(...$validators)->validate(new stdClass());

        static::assertTrue($result->isValid());
        static::assertSame([], $result->all());
    }

    public function testResultsAreMergedInOrderAndEveryValidatorReceivesTheSubject(): void
    {
        $subject = new stdClass();
        $first = new SubjectCheckingValidator($subject, ValidationResult::of(Violation::error('a', 'a.one', 'm')));
        $second = new SubjectCheckingValidator($subject, ValidationResult::valid());
        $third = new SubjectCheckingValidator(
            $subject,
            ValidationResult::of(Violation::warning('c', 'c.two', 'm'), Violation::error('d', 'd.three', 'm')),
        );

        $result = AllOf::of($first, $second, $third)->validate($subject);

        static::assertSame(
            ['error a a.one', 'warning c c.two', 'error d d.three'],
            DomainAssertions::summary($result),
        );
    }
}
