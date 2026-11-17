<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FixturesTest extends TestCase
{
    public function testLoadReturnsTheRawFile(): void
    {
        static::assertStringContainsString('"PARAM_INVALIDE"', Fixtures::load('error_param_invalide'));
    }

    public function testJsonDecodesTheFile(): void
    {
        static::assertSame('PARAM_INVALIDE', Fixtures::json('error_param_invalide')['code']);
    }

    public function testNamesListsTheFixturesByPrefix(): void
    {
        $names = Fixtures::names('error_');

        static::assertContains('error_technical', $names);
        static::assertNotContains('declare_response', $names);
        static::assertSame($names, array_values(array_unique($names)));
        static::assertContains('declare_response', Fixtures::names());
        static::assertSame([], Fixtures::names('nothing_like_this'));
    }

    public function testLoadRefusesAMissingFixture(): void
    {
        $this->expectException(RuntimeException::class);

        Fixtures::load('does_not_exist');
    }
}
