<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Validation;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Input\ChildFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Input\ChildListFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationActivity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Input\ChildminderChildFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Input\ChildminderContractEndFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Input\DeclarationCommonFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Input\DeclarationFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Input\HomeCareContractEndFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Input\AddressFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Input\AdministrativeIdentityFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Input\DeclaredEmployeeFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Input\EmployeeCreationFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Input\EmployeeIdentificationFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\Input\EmployerReferenceFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\Input\EstimateRequestFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;

/**
 * The other way round of {@see TranslationsTest}: the codes really produced by the input factories, fed with hostile
 * values, all have a message. It checks the scanner of {@see ViolationCodes} against the behaviour of the code.
 */
final class ProducedCodesTest extends TestCase
{
    public function testEveryCodeProducedByTheInputFactoriesIsTranslated(): void
    {
        $translated = array_keys(ViolationCodes::messages('en'));
        $produced = [];
        foreach (self::results() as $result) {
            foreach ($result->all() as $violation) {
                $produced[$violation->code()] = true;
            }
        }

        static::assertGreaterThan(40, \count($produced), 'The hostile inputs must reach many rules.');
        static::assertSame([], array_values(array_diff(array_keys($produced), $translated)));
    }

    /**
     * @return iterable<ValidationResult>
     */
    private static function results(): iterable
    {
        $keys = array_values(array_unique(array_map(
            static fn(string $code): string => substr($code, 0, (int) strrpos($code, '.')),
            array_filter(ViolationCodes::all(), static fn(string $code): bool => str_ends_with($code, '.invalid_type')),
        )));
        $inputs = [[]];
        foreach (self::values() as $value) {
            $flat = array_fill_keys($keys, $value);
            $inputs[] = $flat;
            // Two levels of nesting and lists, for the nested maps and the items.
            $inputs[] = array_map(static fn(): array => $flat, $flat);
            $inputs[] = array_map(static fn(): array => [$flat, $flat], $flat);
        }
        foreach ($inputs as $input) {
            yield (new EmployerReferenceFactory())->validate($input);
            yield (new EmployeeIdentificationFactory())->validate($input);
            yield (new EmployeeCreationFactory())->validate($input);
            yield (new AdministrativeIdentityFactory())->validate($input);
            yield (new AddressFactory())->validate($input);
            yield (new DeclaredEmployeeFactory())->validate($input);
            yield (new ChildFactory())->validate($input);
            yield (new ChildListFactory())->validate([$input, $input]);
            yield (new DeclarationCommonFactory())->validate($input);
            yield (new ChildminderChildFactory())->validate($input);
            yield (new ChildminderContractEndFactory())->validate($input);
            yield (new HomeCareContractEndFactory())->validate($input);
            foreach (DeclarationActivity::cases() as $activity) {
                yield (new DeclarationFactory())->validate($activity, $input);
                yield (new EstimateRequestFactory())->validate($activity, $input);
            }
        }
    }

    /**
     * @return list<array<array-key, mixed>|bool|int|string>
     */
    private static function values(): array
    {
        return [
            'ÿ ?!',
            str_repeat('A', 300),
            '0',
            '-1',
            '0.001',
            '99999999999999',
            '-0.5',
            '2026-02-31',
            'not a date',
            '12',
            '123456789012345',
            1,
            1_000_000,
            -5,
            true,
            [],
        ];
    }
}
