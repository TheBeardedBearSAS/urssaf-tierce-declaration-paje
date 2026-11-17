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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildEntitlement;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\ChildCheckMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\EmployeeWireSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\WireAssertions;

final class ChildCheckMapperTest extends TestCase
{
    public function testTheOfficialItemIsDecoded(): void
    {
        $item = JsonDecoder::decode(Fixtures::load('verify_children_response'))->items('verificationEnfantPe')[0];
        $check = ChildCheckMapper::decode($item);

        static::assertNotNull($check);
        static::assertTrue(WireAssertions::present($check->child())->equals(EmployeeWireSamples::child()));
        static::assertSame(1, $check->status()->raw());
        static::assertSame(ChildEntitlement::Entitled, $check->status()->documented());
    }

    public function testAnItemWithoutStatusIsSkipped(): void
    {
        static::assertNull(ChildCheckMapper::decode(JsonDecoder::decode('{"inputEnfantPe":{}}')));
        static::assertNull(ChildCheckMapper::decode(JsonDecoder::decode('{"reponseStatutEnfant":null}')));
    }

    public function testAnItemWithoutChildKeepsTheStatus(): void
    {
        $check = ChildCheckMapper::decode(JsonDecoder::decode('{"reponseStatutEnfant":0}'));

        static::assertNotNull($check);
        static::assertNull($check->child());
        static::assertSame(ChildEntitlement::NotEntitled, $check->status()->documented());
    }

    public function testAnUndocumentedStatusIsKept(): void
    {
        $check = ChildCheckMapper::decode(JsonDecoder::decode('{"reponseStatutEnfant":7}'));

        static::assertNotNull($check);
        static::assertSame(7, $check->status()->raw());
        static::assertNull($check->status()->documented());
    }

    public function testAMalformedStatusIsRefused(): void
    {
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "reponseStatutEnfant" is not an integer.',
            static fn(): mixed => ChildCheckMapper::decode(JsonDecoder::decode('{"reponseStatutEnfant":"1"}')),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnreadableEchoedChildren(): iterable
    {
        yield 'empty object' => ['{}'];
        yield 'not an object' => ['"x"'];
        yield 'lower case name' => ['{"dtNaissanceEnfant":"2015-01-12","nomEnfant":"dupont","prenomEnfant":"JEAN"}'];
        yield 'accented name' => ['{"dtNaissanceEnfant":"2015-01-12","nomEnfant":"DUPONT","prenomEnfant":"JÉRÔME"}'];
        yield 'empty first name' => ['{"dtNaissanceEnfant":"2015-01-12","nomEnfant":"DUPONT","prenomEnfant":""}'];
        yield 'name too long' => ['{"dtNaissanceEnfant":"2015-01-12","nomEnfant":"AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA","prenomEnfant":"JEAN"}'];
        yield 'date placeholder' => ['{"dtNaissanceEnfant":"string","nomEnfant":"DUPONT","prenomEnfant":"JEAN"}'];
    }

    #[DataProvider('provideUnreadableEchoedChildren')]
    public function testAnUnreadableEchoedChildIsNoChildNeverAFailure(string $child): void
    {
        $check = ChildCheckMapper::decode(JsonDecoder::decode('{"reponseStatutEnfant":1,"inputEnfantPe":' . $child . '}'));

        static::assertNotNull($check);
        static::assertNull($check->child());
        static::assertSame(ChildEntitlement::Entitled, $check->status()->documented());
    }
}
