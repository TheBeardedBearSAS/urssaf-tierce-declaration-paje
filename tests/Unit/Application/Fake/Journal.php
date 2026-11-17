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

/**
 * Ordered log shared by the fakes (transactions, saves, publications, gateway calls) to assert the call order.
 */
final class Journal
{
    /** @var list<string> */
    private array $entries = [];

    public function add(string $entry): void
    {
        $this->entries[] = $entry;
    }

    /**
     * @return list<string>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function clear(): void
    {
        $this->entries = [];
    }
}
