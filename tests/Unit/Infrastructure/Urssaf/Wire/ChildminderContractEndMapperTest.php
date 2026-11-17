<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderChild;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderContractEnd;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\ChildminderContractEndMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonEncoder;

final class ChildminderContractEndMapperTest extends TestCase
{
    public function testTheOfficialExamplesAreEncoded(): void
    {
        $zero = DeclarationWireSamples::amount('0');

        WireBodyAssertions::assertBodyIsFixtureSection('predeclare_ama_request_valid', 'finDeContrat', ChildminderContractEndMapper::encode(ChildminderContractEnd::of($zero, $zero, $zero, $zero, $zero)));
        WireBodyAssertions::assertBodyIsFixtureSection('declare_ama_request_valid', 'finDeContrat', ChildminderContractEndMapper::encode(ChildminderContractEnd::of($zero, $zero, $zero, $zero, null)));
    }

    public function testEveryIndemnityHasItsWireName(): void
    {
        $contractEnd = ChildminderContractEnd::of(
            DeclarationWireSamples::amount('1'),
            DeclarationWireSamples::amount('2.5'),
            DeclarationWireSamples::amount('3'),
            DeclarationWireSamples::amount('4'),
            DeclarationWireSamples::amount('5.05'),
        );

        static::assertSame(
            '{"mntIndDepartRetraite":1.00,"mntIndCcp":2.50,"mntIndPreavis":3.00,"mntIndPrecarite":4.00,"mntIndRupture":5.05}',
            JsonEncoder::encode(ChildminderContractEndMapper::encode($contractEnd)),
        );
    }

    public function testNoIndemnityGivesAnEmptyObjectNeverAList(): void
    {
        static::assertSame(
            '{}',
            JsonEncoder::encode(ChildminderContractEndMapper::encode(ChildminderContractEnd::of(null, null, null, null, null))),
        );
    }

    public function testTheDeprecatedChildrenAreEmittedWhenNotEmptyWithTheShapeOfTheDeclaredChildren(): void
    {
        $contractEnd = ChildminderContractEnd::of(
            null,
            null,
            null,
            null,
            DeclarationWireSamples::amount('5'),
            [
                ChildminderChild::of(DeclarationWireSamples::child(), 10, null),
                ChildminderChild::of(DeclarationWireSamples::child(), null, null),
            ],
        );

        static::assertSame(
            '{"mntIndRupture":5.00,"enfants":['
            . '{"dtNaissanceEnfant":"2015-01-12","nomEnfant":"L\'HOMME","prenomEnfant":"JEAN-MICHEL","joursAccueilHandicap":10},'
            . '{"dtNaissanceEnfant":"2015-01-12","nomEnfant":"L\'HOMME","prenomEnfant":"JEAN-MICHEL"}]}',
            JsonEncoder::encode(ChildminderContractEndMapper::encode($contractEnd)),
        );
    }

    public function testEmptyChildrenAreNotSent(): void
    {
        $contractEnd = ChildminderContractEnd::of(null, null, null, null, DeclarationWireSamples::amount('5'), []);

        static::assertArrayNotHasKey('enfants', WireBodyAssertions::decoded(ChildminderContractEndMapper::encode($contractEnd)));
    }
}
