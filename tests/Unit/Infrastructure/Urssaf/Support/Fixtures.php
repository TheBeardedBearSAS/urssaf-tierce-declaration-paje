<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support;

use JsonException;
use RuntimeException;

/**
 * Official wire examples of tests/Fixtures/wire (placeholders such as "string" are kept on purpose, ADR 0001).
 */
final class Fixtures
{
    private function __construct() {}

    /**
     * Raw content of tests/Fixtures/wire/<name>.json.
     */
    public static function load(string $name): string
    {
        $content = file_get_contents(__DIR__ . '/../../../../Fixtures/wire/' . $name . '.json');
        if ($content === false) {
            throw new RuntimeException('Missing fixture ' . $name);
        }

        return $content;
    }

    /**
     * Names of the fixtures whose file name starts with the prefix, sorted.
     *
     * @return list<string>
     */
    public static function names(string $prefix = ''): array
    {
        $files = glob(__DIR__ . '/../../../../Fixtures/wire/' . $prefix . '*.json');
        $names = array_map(static fn(string $file): string => basename($file, '.json'), $files === false ? [] : $files);
        sort($names);

        return $names;
    }

    /**
     * Decoded content of tests/Fixtures/wire/<name>.json.
     *
     * @return array<array-key, mixed>
     *
     * @throws JsonException
     */
    public static function json(string $name): array
    {
        return self::decode(self::load($name));
    }

    /**
     * Decodes a JSON object or array document.
     *
     * @return array<array-key, mixed>
     *
     * @throws JsonException
     */
    public static function decode(string $json): array
    {
        return self::arrayOf(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * Member of a fixture which is itself an object or an array.
     *
     * @return array<array-key, mixed>
     *
     * @throws JsonException
     */
    public static function section(string $name, string $key): array
    {
        return self::arrayOf(self::json($name)[$key] ?? null);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function arrayOf(mixed $decoded): array
    {
        if (!\is_array($decoded)) {
            throw new RuntimeException('Not a JSON object or array');
        }

        return $decoded;
    }
}
