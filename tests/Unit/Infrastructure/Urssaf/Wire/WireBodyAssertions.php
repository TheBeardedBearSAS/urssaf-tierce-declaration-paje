<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use JsonException;
use PHPUnit\Framework\Assert;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonObject;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;

/**
 * Comparison of an encoded body with an official example (tests/Fixtures/wire).
 */
final class WireBodyAssertions
{
    private function __construct() {}

    /**
     * Encodes the body, decodes it again and compares it with the fixture: same values (2.00 equals 2) and the same
     * key order at every level (the order of the official example).
     *
     * @param array<array-key, mixed>|JsonObject $body
     *
     * @throws JsonException
     */
    public static function assertBodyIsFixture(string $fixture, array|JsonObject $body): void
    {
        self::compare($fixture, Fixtures::json($fixture), $body);
    }

    /**
     * Same as {@see self::assertBodyIsFixture()} for a member of the fixture.
     *
     * @param array<array-key, mixed>|JsonObject $body
     *
     * @throws JsonException
     */
    public static function assertBodyIsFixtureSection(string $fixture, string $section, array|JsonObject $body): void
    {
        self::compare($fixture . '.' . $section, Fixtures::section($fixture, $section), $body);
    }

    /**
     * @param array<array-key, mixed>|JsonObject $body
     *
     * @return array<array-key, mixed>
     *
     * @throws JsonException
     */
    public static function decoded(array|JsonObject $body): array
    {
        return Fixtures::decode(JsonEncoder::encode($body));
    }



    /**
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     */
    private static function keyOrder(array $value): array
    {
        return array_map(static fn(mixed $item): ?array => \is_array($item) ? self::keyOrder($item) : null, $value);
    }

    /**
     * @param array<array-key, mixed>            $expected
     * @param array<array-key, mixed>|JsonObject $body
     *
     * @throws JsonException
     */
    private static function compare(string $label, array $expected, array|JsonObject $body): void
    {
        $actual = self::decoded($body);

        Assert::assertEquals($expected, $actual, $label);
        Assert::assertSame(self::keyOrder($expected), self::keyOrder($actual), $label);
    }
}
