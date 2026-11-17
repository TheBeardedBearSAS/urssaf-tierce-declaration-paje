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
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\ChildminderChildMapper;

final class ChildminderChildMapperTest extends TestCase
{
    public function testTheChildIsFollowedByTheTwoCountsInTheOrderOfTheOfficialExample(): void
    {
        $encoded = ChildminderChildMapper::encode(ChildminderChild::of(DeclarationWireSamples::child(), 10, 2));

        static::assertSame(
            [
                'dtNaissanceEnfant' => '2015-01-12',
                'nomEnfant' => "L'HOMME",
                'prenomEnfant' => 'JEAN-MICHEL',
                'joursAccueilHandicap' => 10,
                'joursAccueil24Consecutive' => 2,
            ],
            $encoded,
        );
    }

    public function testAZeroCountIsAValueAndIsSent(): void
    {
        $encoded = ChildminderChildMapper::encode(ChildminderChild::of(DeclarationWireSamples::child(), 0, 0));

        static::assertSame(0, $encoded['joursAccueilHandicap']);
        static::assertSame(0, $encoded['joursAccueil24Consecutive']);
    }

    public function testCountsThatAreNotSetAreOmittedIndependently(): void
    {
        static::assertSame(
            ['dtNaissanceEnfant', 'nomEnfant', 'prenomEnfant'],
            array_keys(ChildminderChildMapper::encode(ChildminderChild::of(DeclarationWireSamples::child(), null, null))),
        );
        static::assertSame(
            ['dtNaissanceEnfant', 'nomEnfant', 'prenomEnfant', 'joursAccueilHandicap'],
            array_keys(ChildminderChildMapper::encode(ChildminderChild::of(DeclarationWireSamples::child(), 3, null))),
        );
        static::assertSame(
            ['dtNaissanceEnfant', 'nomEnfant', 'prenomEnfant', 'joursAccueil24Consecutive'],
            array_keys(ChildminderChildMapper::encode(ChildminderChild::of(DeclarationWireSamples::child(), null, 4))),
        );
    }
}
