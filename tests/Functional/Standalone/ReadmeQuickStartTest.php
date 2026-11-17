<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Standalone;

use Http\Mock\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Stack;

/**
 * The Quick start of the README is executed as written: it must compile and run against a PSR-18 mock client.
 */
final class ReadmeQuickStartTest extends TestCase
{
    public function testTheQuickStartRuns(): void
    {
        $readme = file_get_contents(__DIR__ . '/../../../README.md');
        static::assertIsString($readme);
        $matches = [];
        static::assertSame(1, preg_match('/^## Quick start \(standalone\).*?^```php\n(.*?)^```$/ms', $readme, $matches));

        $httpClient = new Client();
        $httpClient->addResponse(new Response(200, [], Stack::TOKEN_RESPONSE));
        $httpClient->addResponse(new Response(200, [], ''));

        $variables = self::execute($matches[1], $httpClient, new Psr17Factory());

        static::assertSame('registered', $variables['state']);
        $requests = $httpClient->getRequests();
        static::assertCount(2, $requests);
        static::assertSame('POST', $requests[1]->getMethod());
        static::assertSame('/tiersdecl/v1/paje/mandats', $requests[1]->getUri()->getPath());
        static::assertSame('numeroPaje=Y0000000000001&dateNaissance=1980-01-01', $requests[1]->getUri()->getQuery());
    }

    /**
     * @return array<array-key, mixed> the variables left by the snippet
     */
    private static function execute(string $snippet, Client $httpClient, Psr17Factory $psr17Factory): array
    {
        $directory = __DIR__ . '/../../../var';
        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new RuntimeException('The var directory cannot be created.');
        }
        $file = $directory . '/readme-quick-start-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($file, "<?php\n\ndeclare(strict_types=1);\n\n" . $snippet);

        try {
            return (static function () use ($file, $httpClient, $psr17Factory): array {
                require $file;

                return get_defined_vars();
            })();
        } finally {
            unlink($file);
        }
    }
}
