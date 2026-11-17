<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake;

use stdClass;

/**
 * What another writer changed in a keyed array of a store, applied to the current content of the array.
 */
final readonly class ArrayChanges
{
    /**
     * @param array<array-key, mixed> $old     the array before the other writer acted
     * @param array<array-key, mixed> $new     the array after
     * @param array<array-key, mixed> $current the array now (the transaction in progress included)
     *
     * @return array<array-key, mixed> $current with the entries the other writer added or changed, without the ones it removed
     */
    public static function apply(array $old, array $new, array $current): array
    {
        $changed = array_filter(
            $new,
            static fn(mixed $value, int|string $key): bool => !\array_key_exists($key, $old) || self::differ($old[$key], $value),
            ARRAY_FILTER_USE_BOTH,
        );

        return array_diff_key(
            array_replace($current, array_map(static fn(mixed $value): mixed => \is_object($value) && !$value instanceof stdClass ? clone $value : $value, $changed)),
            array_diff_key($old, $new),
        );
    }

    private static function differ(mixed $a, mixed $b): bool
    {
        return $a instanceof stdClass ? $a !== $b : serialize($a) !== serialize($b);
    }
}
