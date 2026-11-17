<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support;

/**
 * Reads the tables printed by the console commands.
 */
final class Output
{
    /**
     * The rows of the tables of a command output (header rows included), cells trimmed.
     *
     * @return list<list<string>>
     */
    public static function rows(string $display): array
    {
        $rows = [];
        foreach (explode("\n", $display) as $line) {
            if (!str_starts_with($line, '|')) {
                continue;
            }
            $rows[] = array_map(trim(...), explode('|', trim($line, '|')));
        }

        return $rows;
    }
}
