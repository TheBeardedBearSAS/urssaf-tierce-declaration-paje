<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonObject;

final class JsonObjectTest extends TestCase
{
    public function testItExposesItsMembers(): void
    {
        static::assertSame(['a' => 1, 'b' => [2]], JsonObject::of(['a' => 1, 'b' => [2]])->members());
        static::assertSame([], JsonObject::of([])->members());
    }
}
