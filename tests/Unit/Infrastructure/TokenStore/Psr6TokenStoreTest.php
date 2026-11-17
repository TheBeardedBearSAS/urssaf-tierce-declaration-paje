<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\TokenStore;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\AccessToken;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\TokenStore\CacheKey;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\TokenStore\Psr6TokenStore;

final class Psr6TokenStoreTest extends TestCase
{
    private const EXPIRES_AT = '2100-01-01T00:00:00+00:00';

    public function testMissReturnsNull(): void
    {
        $store = new Psr6TokenStore(new ArrayAdapter());

        static::assertNull($store->get('tenant-a'));
    }

    public function testSavedTokenIsRestored(): void
    {
        $store = new Psr6TokenStore(new ArrayAdapter());

        $store->save('tenant-a', self::token('tok3n'));

        $restored = $store->get('tenant-a');
        static::assertInstanceOf(AccessToken::class, $restored);
        static::assertSame('tok3n', $restored->value());
        static::assertSame(4_102_444_800, $restored->expiresAt()->toDateTimeImmutable()->getTimestamp());
    }

    public function testSaveSetsTheCacheItemExpiryToTheTokenExpiry(): void
    {
        $pool = new ArrayAdapter();
        $store = new Psr6TokenStore($pool);

        $store->save('tenant-a', self::token('tok3n'));

        $metadata = $pool->getItem(CacheKey::of('tenant-a'))->getMetadata();
        static::assertSame(4_102_444_800.0, $metadata['expiry']);
    }

    public function testSaveReplacesThePreviousToken(): void
    {
        $store = new Psr6TokenStore(new ArrayAdapter());

        $store->save('tenant-a', self::token('initial'));
        $store->save('tenant-a', self::token('renewed'));

        $restored = $store->get('tenant-a');
        static::assertNotNull($restored);
        static::assertSame('renewed', $restored->value());
    }

    public function testDeleteRemovesTheToken(): void
    {
        $store = new Psr6TokenStore(new ArrayAdapter());
        $store->save('tenant-a', self::token('tok3n'));

        $store->delete('tenant-a');

        static::assertNull($store->get('tenant-a'));
    }

    public function testKeysWithReservedCharactersRoundTrip(): void
    {
        $store = new Psr6TokenStore(new ArrayAdapter());

        $store->save('tenant:prod/{a}', self::token('tok3n'));

        $restored = $store->get('tenant:prod/{a}');
        static::assertNotNull($restored);
        static::assertSame('tok3n', $restored->value());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function corruptedPayloads(): iterable
    {
        yield 'not an array' => ['garbage'];
        yield 'missing expiry' => [['value' => 'tok3n']];
        yield 'blank value' => [['value' => ' ', 'expiresAt' => 4_102_444_800]];
    }

    #[DataProvider('corruptedPayloads')]
    public function testCorruptedEntryIsTreatedAsAMissAndDeleted(mixed $payload): void
    {
        $pool = new ArrayAdapter();
        $item = $pool->getItem('tenant-a');
        $item->set($payload);
        $pool->save($item);
        $store = new Psr6TokenStore($pool);

        static::assertNull($store->get('tenant-a'));
        static::assertFalse($pool->hasItem('tenant-a'));
    }

    public function testMissLeavesThePoolUntouched(): void
    {
        $pool = new RecordingPool();

        static::assertNull((new Psr6TokenStore($pool))->get('tenant-a'));
        static::assertSame([], $pool->deleted());
    }

    private static function token(string $value): AccessToken
    {
        return AccessToken::of($value, Instant::fromDateTime(new DateTimeImmutable(self::EXPIRES_AT)));
    }
}
