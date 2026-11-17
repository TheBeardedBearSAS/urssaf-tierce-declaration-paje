<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Command;

use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Output;

/**
 * The "Field | Value" table of a command output as a map.
 */
final class Fields
{
    private function __construct() {}

    /**
     * @return array<string, string>
     */
    public static function of(string $display): array
    {
        $fields = [];
        foreach (Output::rows($display) as $row) {
            if (\count($row) !== 2 || $row[0] === 'Field') {
                continue;
            }

            $fields[$row[0]] = $row[1];
        }

        return $fields;
    }

    /**
     * The path of a payload file of tests/Functional/Symfony/Command/payloads.
     */
    public static function payload(string $name): string
    {
        return __DIR__ . '/payloads/' . $name;
    }
}
