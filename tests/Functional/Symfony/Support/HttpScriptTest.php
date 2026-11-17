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

use Http\Mock\Client;
use Nyholm\Psr7\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\UrssafEndpoints;

/**
 * The scripted platform answers each of the twelve operations and the token route, counts the calls per route and can be
 * switched to a status or to a transport failure.
 */
final class HttpScriptTest extends TestCase
{
    private const BASE = 'https://api.example.test' . UrssafEndpoints::API_BASE_PATH;

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function routes(): iterable
    {
        yield HttpScript::ROUTE_AUTHENTICATION => [HttpScript::ROUTE_AUTHENTICATION, 'POST', 'https://api.example.test' . UrssafEndpoints::TOKEN_PATH];
        yield HttpScript::ROUTE_020 => [HttpScript::ROUTE_020, 'GET', self::BASE . '/employeurs/Y2926822480004/verifier'];
        yield HttpScript::ROUTE_021 => [HttpScript::ROUTE_021, 'POST', self::BASE . '/salaries/verifier'];
        yield HttpScript::ROUTE_022 => [HttpScript::ROUTE_022, 'POST', self::BASE . '/employeurs/Y2926822480004/enfants/verifier'];
        yield HttpScript::ROUTE_023 => [HttpScript::ROUTE_023, 'POST', self::BASE . '/employeurs/Y2926822480004/salarie/associer'];
        yield HttpScript::ROUTE_030 => [HttpScript::ROUTE_030, 'POST', self::BASE . '/mandats'];
        yield HttpScript::ROUTE_031 => [HttpScript::ROUTE_031, 'DELETE', self::BASE . '/mandats'];
        yield HttpScript::ROUTE_040 => [HttpScript::ROUTE_040, 'POST', self::BASE . '/ama/estimer'];
        yield HttpScript::ROUTE_041 => [HttpScript::ROUTE_041, 'POST', self::BASE . '/ged/estimer'];
        yield HttpScript::ROUTE_045 => [HttpScript::ROUTE_045, 'POST', self::BASE . '/employeurs/Y2926822480004/ama/predeclarer'];
        yield HttpScript::ROUTE_046 => [HttpScript::ROUTE_046, 'POST', self::BASE . '/employeurs/Y2926822480004/ged/predeclarer'];
        yield HttpScript::ROUTE_050 => [HttpScript::ROUTE_050, 'POST', self::BASE . '/employeurs/Y2926822480004/ama/declarer'];
        yield HttpScript::ROUTE_051 => [HttpScript::ROUTE_051, 'POST', self::BASE . '/employeurs/Y2926822480004/ged/declarer'];
    }

    #[DataProvider('routes')]
    public function testEachRouteAnswersAndCountsItsCallsOnly(string $route, string $method, string $uri): void
    {
        [$client, $script] = self::platform();

        $response = $client->sendRequest(new Request($method, $uri));

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(1, $script->calls($route));
        foreach (array_keys(iterator_to_array(self::routes(), true)) as $other) {
            static::assertSame($other === $route ? 1 : 0, $script->calls($other), $other);
        }
    }

    public function testARouteAnswersAGivenStatusAndFixture(): void
    {
        [$client, $script] = self::platform();
        $script->respond(HttpScript::ROUTE_050, 400, '{"codeErreur":"ER_API_050_0070"}', ['Retry-After' => '3']);

        $response = $client->sendRequest(new Request('POST', self::BASE . '/employeurs/Y1869005780009/ama/declarer'));
        static::assertSame(400, $response->getStatusCode());
        static::assertSame('3', $response->getHeaderLine('Retry-After'));
        static::assertSame('{"codeErreur":"ER_API_050_0070"}', (string) $response->getBody());

        $script->respondWithFixture(HttpScript::ROUTE_050, 200, 'declare_response_valid.json');
        $response = $client->sendRequest(new Request('POST', self::BASE . '/employeurs/Y1869005780009/ama/declarer'));
        static::assertSame(200, $response->getStatusCode());
        static::assertSame(HttpScript::fixture('declare_response_valid.json'), (string) $response->getBody());
    }

    public function testARouteCanFailLikeABrokenNetwork(): void
    {
        [$client, $script] = self::platform();
        $script->failTransport(HttpScript::ROUTE_020);

        $this->expectException(ClientExceptionInterface::class);
        $this->expectExceptionMessage('connection refused');

        try {
            $client->sendRequest(new Request('GET', self::BASE . '/employeurs/Y1869005780009/verifier'));
        } finally {
            static::assertSame(1, $script->calls(HttpScript::ROUTE_020));
        }
    }

    /**
     * @return array{Client, HttpScript}
     */
    private static function platform(): array
    {
        $client = new Client();

        return [$client, new HttpScript($client)];
    }
}
