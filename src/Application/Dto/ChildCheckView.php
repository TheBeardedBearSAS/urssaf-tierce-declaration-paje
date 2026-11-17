<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildCheck;

/**
 * Read model of one child checked with method 022: the raw entitlement status and its documented meaning (null for an
 * undocumented value). The child identity is never returned (ADR 0007): the checks come in the order of the answer.
 */
final readonly class ChildCheckView
{
    private function __construct(
        public int $status,
        public ?string $entitlement,
    ) {}

    private static function fromCheck(ChildCheck $check): self
    {
        return new self($check->status()->raw(), $check->status()->documented()?->name);
    }

    /**
     * @param list<ChildCheck> $checks
     *
     * @return list<self>
     */
    public static function fromChecks(array $checks): array
    {
        $views = [];
        foreach ($checks as $check) {
            $views[] = self::fromCheck($check);
        }

        return $views;
    }
}
