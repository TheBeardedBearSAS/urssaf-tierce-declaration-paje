<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Port\Urssaf;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\RejectionKind;

final class RejectionKindTest extends TestCase
{
    public function testValuesAreTheAdr0005List(): void
    {
        static::assertSame(
            [
                'invalid_parameter',
                'employer_not_found',
                'birth_date_mismatch',
                'employee_birth_date_mismatch',
                'mandate_missing',
                'mandate_held_elsewhere',
                'duplicate_declaration',
                'declaration_window',
                'employee_not_associated',
                'business_rule',
                'technical',
                'unknown',
            ],
            array_map(static fn(RejectionKind $kind): string => $kind->value, RejectionKind::cases()),
        );
    }

    public function testFromValueRoundTrips(): void
    {
        static::assertSame(RejectionKind::DuplicateDeclaration, RejectionKind::from('duplicate_declaration'));
        static::assertNull(RejectionKind::tryFrom('ER_API_050_0070'));
    }
}
