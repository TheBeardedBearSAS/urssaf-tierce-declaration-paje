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

use ReflectionObject;
use ReflectionProperty;
use stdClass;

/**
 * Captured state of the in-memory stores of a test (the arrays of the repositories and of the outbox, and those of the
 * in-memory objects they hold: their version tokens). Used by {@see SnapshottingTransactionManager} to roll back.
 *
 * The state is read with reflection: the stores are final and expose nothing to snapshot. Aggregates are cloned; version
 * tokens (stdClass) are kept as they are, their identity is what the repositories compare.
 */
final readonly class StoreSnapshots
{
    private const IN_MEMORY = 'TheBeardedBear\\UrssafTierceDeclarationPaje\\Infrastructure\\Persistence\\InMemory\\';

    /**
     * @param list<array{object, ReflectionProperty, array<array-key, mixed>}> $entries
     */
    private function __construct(private array $entries) {}

    public static function of(object ...$stores): self
    {
        $entries = [];
        foreach ($stores as $store) {
            array_push($entries, ...self::capture($store));
        }

        return new self($entries);
    }

    /**
     * Puts the captured state back in the stores.
     */
    public function restore(): void
    {
        foreach ($this->entries as [$store, $property, $values]) {
            $property->setValue($store, self::copy($values));
        }
    }

    /**
     * Applies to the stores (as they are now) what another writer changed between this state and $after: the entries it
     * added or changed are copied, the ones it removed are removed.
     */
    public function mergeChangesUntil(self $after): void
    {
        foreach ($this->entries as $index => [$store, $property, $old]) {
            $new = $after->entries[$index][2];
            /** @var array<array-key, mixed> $current */
            $current = $property->getValue($store);
            $property->setValue($store, ArrayChanges::apply($old, $new, $current));
        }
    }

    /**
     * @return list<array{object, ReflectionProperty, array<array-key, mixed>}>
     */
    private static function capture(object $store): array
    {
        $entries = [];
        foreach ((new ReflectionObject($store))->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }
            array_push($entries, ...self::entriesOf($store, $property, $property->getValue($store)));
        }

        return $entries;
    }

    /**
     * @return list<array{object, ReflectionProperty, array<array-key, mixed>}>
     */
    private static function entriesOf(object $store, ReflectionProperty $property, mixed $value): array
    {
        return match (true) {
            \is_array($value) && !$property->isReadOnly() => [[$store, $property, self::copy($value)]],
            \is_object($value) && str_starts_with($value::class, self::IN_MEMORY) => self::capture($value),
            default => [],
        };
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private static function copy(array $values): array
    {
        return array_map(self::copyOf(...), $values);
    }

    private static function copyOf(mixed $value): mixed
    {
        return \is_object($value) && !$value instanceof stdClass ? clone $value : $value;
    }
}
