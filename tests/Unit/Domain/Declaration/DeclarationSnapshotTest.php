<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationActivity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationFingerprint;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationSnapshot;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeIdentificationKind;

final class DeclarationSnapshotTest extends TestCase
{
    public function testKeepsTheNonPersonalPartOfAChildminderDeclarationIdentifiedByNumber(): void
    {
        $declaration = RecordSamples::childminder();

        $snapshot = DeclarationSnapshot::fromDeclaration(RecordSamples::employerNumber(), $declaration);

        static::assertTrue(RecordSamples::employerNumber()->equals($snapshot->employerNumber()));
        static::assertSame(DeclarationActivity::Childminder, $snapshot->activity());
        static::assertSame($declaration->common()->period(), $snapshot->period());
        static::assertSame(EmployeeIdentificationKind::Number, $snapshot->employeeIdentificationKind());
        static::assertTrue(RecordSamples::employeeNumber()->equals($snapshot->employeeNumber() ?? static::fail('Expected an employee number.')));
        static::assertTrue($snapshot->fingerprint()->equals(DeclarationFingerprint::of($declaration)));
    }

    public function testKeepsNoEmployeeNumberForANir(): void
    {
        $snapshot = DeclarationSnapshot::fromDeclaration(RecordSamples::employerNumber(), RecordSamples::homeCare(RecordSamples::nirEmployee()));

        static::assertSame(DeclarationActivity::HomeCare, $snapshot->activity());
        static::assertSame(EmployeeIdentificationKind::Nir, $snapshot->employeeIdentificationKind());
        static::assertNull($snapshot->employeeNumber());
    }

    public function testSameContentIsDecidedByTheFingerprint(): void
    {
        $base = RecordSamples::snapshot();

        static::assertTrue($base->hasSameContentAs(RecordSamples::snapshot(RecordSamples::childminder(RecordSamples::nirEmployee()))));
        static::assertFalse($base->hasSameContentAs(RecordSamples::snapshot(RecordSamples::childminder(null, RecordSamples::common(101)))));
    }
}
