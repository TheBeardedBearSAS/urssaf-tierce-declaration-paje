<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Repository;

use ArrayIterator;
use PHPUnit\Framework\TestCase;
use stdClass;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\Hydration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

final class HydrationTest extends TestCase
{
    public function testItKeepsOnlyTheInstancesOfTheExpectedClassInOrder(): void
    {
        $first = TenantId::fromString('tenant-1');
        $second = TenantId::fromString('tenant-2');

        $all = Hydration::all(new ArrayIterator([$first, 'text', 12, null, new stdClass(), $second]), TenantId::class);

        static::assertSame([$first, $second], $all);
    }

    public function testItReturnsTheFirstInstanceOrNull(): void
    {
        $first = TenantId::fromString('tenant-1');

        static::assertSame($first, Hydration::first(['text', $first, TenantId::fromString('tenant-2')], TenantId::class));
        static::assertNull(Hydration::first(['text'], TenantId::class));
        static::assertNull(Hydration::first([], TenantId::class));
    }
}
