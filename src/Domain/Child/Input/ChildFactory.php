<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Input;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\UpperCaseName;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\RawFields;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;

/**
 * Turns the raw data of one child into a {@see Child}, reporting every violation (notification pattern, ADR 0000
 * item 4).
 *
 * Input keys: "birth_date" (Y-m-d), "last_name", "first_name" (unaccented upper case).
 */
final readonly class ChildFactory
{
    /**
     * @param array<array-key, mixed> $input
     */
    public function validate(#[SensitiveParameter] array $input): ValidationResult
    {
        return RawFields::validate($input, $this->parse(...));
    }

    /**
     * @param array<array-key, mixed> $input
     *
     * @throws ValidationFailed with every violation
     */
    public function fromInput(#[SensitiveParameter] array $input): Child
    {
        return RawFields::resolve($input, $this->parse(...));
    }

    /**
     * @internal
     */
    public function parse(RawFields $fields): ?Child
    {
        $birthDate = $fields->required('birth_date', CalendarDate::fromString(...));
        $lastName = $fields->required('last_name', static fn(string $raw): UpperCaseName => UpperCaseName::of($raw, 'last_name'));
        $firstName = $fields->required('first_name', static fn(string $raw): UpperCaseName => UpperCaseName::of($raw, 'first_name'));

        return $birthDate instanceof CalendarDate && $lastName instanceof UpperCaseName && $firstName instanceof UpperCaseName
            ? Child::of($birthDate, $lastName, $firstName)
            : null;
    }
}
