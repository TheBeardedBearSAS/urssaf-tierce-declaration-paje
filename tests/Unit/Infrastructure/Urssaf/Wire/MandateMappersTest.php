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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\MandateRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\MandateResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\QueryEncoding;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\WireAssertions;

final class MandateMappersTest extends TestCase
{
    public function testTheQueryIsTheOfficialExample(): void
    {
        $employer = EmployerReference::of(PajeEmployerNumber::of('Y2926822480004'), CalendarDate::fromString('1988-01-30'));
        $query = MandateRequestMapper::query($employer);

        static::assertSame(['numeroPaje' => 'Y2926822480004', 'dateNaissance' => '1988-01-30'], $query);
        static::assertSame('numeroPaje=Y2926822480004&dateNaissance=1988-01-30', QueryEncoding::build($query));
    }

    public function testAnEmptyAnswerIsAccepted(): void
    {
        foreach (['', '  ', "\n", '{}', ' {} '] as $body) {
            MandateResponseMapper::assertEmpty($body);
        }

        static::expectNotToPerformAssertions();
    }

    public function testAnythingElseIsRefusedWithoutTheBody(): void
    {
        $exception = WireAssertions::caught(static function (): void {
            MandateResponseMapper::assertEmpty('{"a":"secret"}');
        });

        static::assertSame('Unexpected URSSAF response: expected an empty body.', $exception->getMessage());
        static::assertStringNotContainsString('secret', $exception->getMessage());
    }

    public function testAListIsNotAnEmptyAnswer(): void
    {
        foreach (['[]', ' [] ', '[1]', '[{}]'] as $body) {
            WireAssertions::assertUnexpected(
                'Unexpected URSSAF response: body is not a JSON object.',
                static function () use ($body): void {
                    MandateResponseMapper::assertEmpty($body);
                },
            );
        }
    }

    public function testInvalidJsonIsRefused(): void
    {
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: body is not valid JSON.',
            static function (): void {
                MandateResponseMapper::assertEmpty('not json');
            },
        );
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: body is neither a JSON object nor a JSON array.',
            static function (): void {
                MandateResponseMapper::assertEmpty('"ok"');
            },
        );
    }

    public function testTheRawInputIsSensitiveMandateResponseMapper(): void
    {
        DomainAssertions::assertSensitiveParameters(MandateResponseMapper::class, 'assertEmpty', ['body']);
    }
}
