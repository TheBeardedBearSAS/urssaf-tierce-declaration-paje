<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Contract;

use TheBeardedBear\UrssafTierceDeclarationPaje\SpecVersion;

/**
 * What the sandbox answered for each open point of docs/api-reference.md section 5, written to
 * var/contract/sandbox-report.json. Values come from Exchange (whitelisted) or from SDK enums, booleans, integers and
 * catalogue codes: never a Pajemploi number, a NIR, a birth date, a name, a reference, a token, a message, a
 * description or a query string.
 */
final class SandboxReport
{
    public const OBSERVED = 'observed';
    public const NOT_OBSERVED = 'not_observed';
    public const NOT_RUN = 'not_run';

    /** The goals of the first contract run, in the order of docs/api-reference.md section 5. */
    public const POINTS = [
        'token-host',
        'mandate-prerequisite',
        'estimate-context-free',
        'error-codes',
        '023-required-fields',
        'retry-safety-023',
        'retry-safety-030',
        'retry-safety-031',
        'response-decimals',
        'online-account-state',
        'unknown-employer-020',
        'unknown-employee-021',
        'children-022',
        'predeclaration',
        'declaration',
        'error-body-shape',
        'headers',
        'quota-429',
    ];

    private const RANKS = [self::NOT_RUN => 0, self::NOT_OBSERVED => 1, self::OBSERVED => 2];

    /** @var null|array<string, array{point: string, state: string, value: mixed, test: string}> */
    private static ?array $points = null;
    private static ?string $path = null;
    private static string $tokenHost = 'unknown';
    private static string $baseUriHost = 'unknown';
    private static bool $writesAllowed = false;

    /**
     * Records an observation; a stronger state replaces a weaker one (observed > not_observed > not_run), whatever the
     * (random) order of the tests.
     */
    public static function record(string $point, string $state, mixed $value, string $test): void
    {
        $points = self::points();
        $current = $points[$point] ?? null;
        if (null !== $current && self::RANKS[$state] < self::RANKS[$current['state']]) {
            return;
        }

        self::$points = [...$points, $point => ['point' => $point, 'state' => $state, 'value' => $value, 'test' => $test]];
    }

    /**
     * Adds one entry to the map of a point (one entry per operation, per call...), keeping the others: the tests run in
     * a random order and several of them feed the same point.
     */
    public static function merge(string $point, string $key, mixed $value, string $test): void
    {
        // @mago-expect analysis:mixed-assignment A recorded value is untyped: it is narrowed to an array right below.
        $current = self::points()[$point]['value'] ?? null;
        $map = \is_array($current) ? $current : [];
        $map[$key] = $value;
        ksort($map);

        self::record($point, self::OBSERVED, $map, $test);
    }

    public static function describeRun(string $baseUriHost, string $tokenHost, bool $writesAllowed): void
    {
        self::$baseUriHost = $baseUriHost;
        self::$tokenHost = $tokenHost;
        self::$writesAllowed = $writesAllowed;
    }

    /**
     * @return array<string, array{point: string, state: string, value: mixed, test: string}>
     */
    public static function points(): array
    {
        if (null === self::$points) {
            self::$points = [];
            foreach (self::POINTS as $point) {
                self::$points[$point] = ['point' => $point, 'state' => self::NOT_RUN, 'value' => null, 'test' => ''];
            }
        }

        return self::$points;
    }

    /**
     * Rewrites the file with everything recorded so far by the process (the first flush replaces any previous run's file).
     */
    public static function flush(): void
    {
        $path = self::path();
        $directory = \dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0o775, true);
        }

        $points = [];
        foreach (self::points() as $id => $entry) {
            $points[$id] = ['state' => $entry['state'], 'value' => $entry['value'], 'test' => $entry['test']];
        }

        $content = json_encode([
            'schema' => 1,
            'generatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
            'specVersion' => SpecVersion::CURRENT,
            'baseUriHost' => self::$baseUriHost,
            'tokenHost' => self::$tokenHost,
            'writesAllowed' => self::$writesAllowed,
            'points' => $points,
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);

        file_put_contents($path, $content . "\n");
    }

    public static function usePath(?string $path): void
    {
        self::$path = $path;
    }

    /**
     * Empties the report and returns what it held, for the offline tests of the report itself to put back afterwards
     * (they must not wipe the observations of live tests of the same process).
     *
     * @return array{points: null|array<string, array{point: string, state: string, value: mixed, test: string}>, path: null|string, tokenHost: string, baseUriHost: string, writesAllowed: bool}
     */
    public static function reset(): array
    {
        $saved = [
            'points' => self::$points,
            'path' => self::$path,
            'tokenHost' => self::$tokenHost,
            'baseUriHost' => self::$baseUriHost,
            'writesAllowed' => self::$writesAllowed,
        ];
        self::$points = null;
        self::$path = null;
        self::$tokenHost = 'unknown';
        self::$baseUriHost = 'unknown';
        self::$writesAllowed = false;

        return $saved;
    }

    /**
     * @param array{points: null|array<string, array{point: string, state: string, value: mixed, test: string}>, path: null|string, tokenHost: string, baseUriHost: string, writesAllowed: bool} $saved
     */
    public static function restore(array $saved): void
    {
        self::$points = $saved['points'];
        self::$path = $saved['path'];
        self::$tokenHost = $saved['tokenHost'];
        self::$baseUriHost = $saved['baseUriHost'];
        self::$writesAllowed = $saved['writesAllowed'];
    }

    private static function path(): string
    {
        return self::$path ?? \dirname(__DIR__, 2) . '/var/contract/sandbox-report.json';
    }
}
