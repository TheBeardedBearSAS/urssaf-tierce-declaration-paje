<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonObject;

/**
 * One method per case.
 *
 * @mago-expect lint:too-many-methods
 */
final class ApiRequestTest extends TestCase
{
    public function testGetCarriesAQueryAndNoBody(): void
    {
        $request = ApiRequest::get(Operation::VerifyEmployer, '/employeurs/1/verifier', ['a' => 'b']);

        static::assertSame(Operation::VerifyEmployer, $request->operation());
        static::assertSame('GET', $request->method());
        static::assertSame('/employeurs/1/verifier', $request->path());
        static::assertSame(['a' => 'b'], $request->query());
        static::assertNull($request->jsonBody());
    }

    public function testGetDefaultsToAnEmptyQuery(): void
    {
        static::assertSame([], ApiRequest::get(Operation::VerifyEmployer, '/p')->query());
    }

    public function testPostEncodesAnArrayBody(): void
    {
        $request = ApiRequest::post(Operation::DeclareChildminder, '/p', [], ['k' => 1, 'l' => [1, 2]]);

        static::assertSame(Operation::DeclareChildminder, $request->operation());
        static::assertSame('POST', $request->method());
        static::assertSame('/p', $request->path());
        static::assertSame([], $request->query());
        static::assertSame('{"k":1,"l":[1,2]}', $request->jsonBody());
    }

    public function testPostEncodesAnEmptyJsonObjectAsBraces(): void
    {
        static::assertSame('{}', ApiRequest::post(Operation::VerifyEmployee, '/p', [], JsonObject::of([]))->jsonBody());
    }

    public function testPostKeepsAnEmptyListAsAnArray(): void
    {
        static::assertSame('[]', ApiRequest::post(Operation::VerifyEmployee, '/p', [], [])->jsonBody());
    }

    public function testPostCarriesAQueryAndAnObjectBody(): void
    {
        $request = ApiRequest::post(Operation::VerifyEmployee, '/p', ['numero' => '1'], JsonObject::of(['a' => 'b']));

        static::assertSame(['numero' => '1'], $request->query());
        static::assertSame('{"a":"b"}', $request->jsonBody());
    }

    public function testPostWithoutBodyHasNoBodyAndNoQueryByDefault(): void
    {
        $request = ApiRequest::post(Operation::RegisterMandate, '/mandats');

        static::assertSame([], $request->query());
        static::assertNull($request->jsonBody());
    }

    public function testPostCarriesAQueryAndNoBody(): void
    {
        $request = ApiRequest::post(Operation::RegisterMandate, '/mandats', ['numeroPaje' => '1']);

        static::assertSame('POST', $request->method());
        static::assertSame(['numeroPaje' => '1'], $request->query());
        static::assertNull($request->jsonBody());
    }

    public function testDeleteCarriesAQueryAndNoBody(): void
    {
        $request = ApiRequest::delete(Operation::CancelMandate, '/mandats', ['numeroPaje' => '1']);

        static::assertSame(Operation::CancelMandate, $request->operation());
        static::assertSame('DELETE', $request->method());
        static::assertSame('/mandats', $request->path());
        static::assertSame(['numeroPaje' => '1'], $request->query());
        static::assertNull($request->jsonBody());
    }

    public function testDeleteDefaultsToAnEmptyQuery(): void
    {
        static::assertSame([], ApiRequest::delete(Operation::CancelMandate, '/mandats')->query());
    }

    public function testDebugInfoShowsQueryNamesOnly(): void
    {
        $request = ApiRequest::post(
            Operation::VerifyEmployee,
            '/employeurs/123456789012/salarie',
            ['nom' => 'FICTIF', 'dateNaissance' => '1990-01-01'],
            ['iban' => 'x'],
        );

        static::assertSame(
            [
                'operation' => '021',
                'method' => 'POST',
                'path' => '***',
                'query' => ['nom', 'dateNaissance'],
                'jsonBody' => '***',
            ],
            $request->__debugInfo(),
        );
        static::assertStringNotContainsString('FICTIF', print_r($request, true));
        static::assertStringNotContainsString('123456789012', print_r($request, true));
    }

    public function testDebugInfoOfARequestWithoutBodyNorQuery(): void
    {
        static::assertSame(
            ['operation' => '031', 'method' => 'DELETE', 'path' => '***', 'query' => [], 'jsonBody' => null],
            ApiRequest::delete(Operation::CancelMandate, '/mandats', [])->__debugInfo(),
        );
    }
}
