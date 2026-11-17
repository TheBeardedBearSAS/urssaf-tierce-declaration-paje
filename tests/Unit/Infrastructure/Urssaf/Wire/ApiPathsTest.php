<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\ApiPaths;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;

final class ApiPathsTest extends TestCase
{
    private const EMPLOYER = 'Y2926822480004';

    public function testTheTwelveOperationsMatchTheSwaggerPaths(): void
    {
        $employer = PajeEmployerNumber::of(self::EMPLOYER);
        $base = '/employeurs/Y2926822480004';

        static::assertSame($base . '/verifier', ApiPaths::verifyEmployer($employer));
        static::assertSame('/salaries/verifier', ApiPaths::verifyEmployee());
        static::assertSame($base . '/enfants/verifier', ApiPaths::verifyChildren($employer));
        static::assertSame($base . '/salarie/associer', ApiPaths::associateEmployee($employer));
        static::assertSame('/ama/estimer', ApiPaths::estimateChildminder());
        static::assertSame('/ged/estimer', ApiPaths::estimateHomeCare());
        static::assertSame($base . '/ama/predeclarer', ApiPaths::preDeclareChildminder($employer));
        static::assertSame($base . '/ged/predeclarer', ApiPaths::preDeclareHomeCare($employer));
        static::assertSame($base . '/ama/declarer', ApiPaths::declareChildminder($employer));
        static::assertSame($base . '/ged/declarer', ApiPaths::declareHomeCare($employer));
        static::assertSame('/mandats', ApiPaths::registerMandate());
        static::assertSame('/mandats', ApiPaths::cancelMandate());
    }

    public function testEveryPathIsDeclaredBySwagger(): void
    {
        $swagger = Fixtures::decode((string) file_get_contents(__DIR__ . '/../../../../../docs/openapi/td-paje-prd.json'));
        static::assertIsArray($swagger);
        static::assertIsArray($swagger['paths']);
        $employer = PajeEmployerNumber::of(self::EMPLOYER);
        $template = static fn(string $path): string => str_replace(self::EMPLOYER, '{numeroPajeEmployeur}', $path);

        $paths = [
            $template(ApiPaths::verifyEmployer($employer)),
            ApiPaths::verifyEmployee(),
            $template(ApiPaths::verifyChildren($employer)),
            $template(ApiPaths::associateEmployee($employer)),
            ApiPaths::estimateChildminder(),
            ApiPaths::estimateHomeCare(),
            $template(ApiPaths::preDeclareChildminder($employer)),
            $template(ApiPaths::preDeclareHomeCare($employer)),
            $template(ApiPaths::declareChildminder($employer)),
            $template(ApiPaths::declareHomeCare($employer)),
            ApiPaths::registerMandate(),
            ApiPaths::cancelMandate(),
        ];

        foreach ($paths as $path) {
            static::assertArrayHasKey($path, $swagger['paths']);
        }
        static::assertCount(11, array_unique($paths));
        static::assertCount(11, $swagger['paths']);
    }

    public function testTheEmployerSegmentIsPercentEncoded(): void
    {
        static::assertSame('/employeurs/Y2926822480004', ApiPaths::employer(PajeEmployerNumber::of(self::EMPLOYER)));
        // 14 characters, none of them URL-safe except the first and the last (no character set is enforced).
        static::assertSame('/employeurs/A%2FB%20C%3F%23%25%2B%26%3D%3Ax%3B', ApiPaths::employer(PajeEmployerNumber::of('A/B C?#%+&=:x;')));
    }
}
