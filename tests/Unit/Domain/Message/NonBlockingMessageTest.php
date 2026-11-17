<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Message;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message\NonBlockingCode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message\NonBlockingMessage;

final class NonBlockingMessageTest extends TestCase
{
    /**
     * @return iterable<string, array{string, NonBlockingCode}>
     */
    public static function provideCodes(): iterable
    {
        yield 'ER_API_020_NB_0000' => ['ER_API_020_NB_0000', NonBlockingCode::EmployerNotAuthorised];
        yield 'ER_API_020_NB_0010' => ['ER_API_020_NB_0010', NonBlockingCode::EmployerBankDetailsInvalid];
        yield 'ER_API_021_NB_0000' => ['ER_API_021_NB_0000', NonBlockingCode::EmployeeNotVerified];
        yield 'ER_API_022_NB_0000' => ['ER_API_022_NB_0000', NonBlockingCode::ChildUnknown];
        yield 'ER_API_041_NB_0010' => ['ER_API_041_NB_0010', NonBlockingCode::ChildNoLongerEligible];
        yield 'ER_API_041_NB_0020' => ['ER_API_041_NB_0020', NonBlockingCode::CalculationModeInconsistent];
        yield 'ER_API_023_NB_0000' => ['ER_API_023_NB_0000', NonBlockingCode::EmployeeAlreadyAssociated];
        yield 'ER_API_023_DATE_AGREMENT_NB_0000' => ['ER_API_023_DATE_AGREMENT_NB_0000', NonBlockingCode::AgreementYearInconsistentWithBirth];
        yield 'ER_API_023_DATE_AGREMENT_NB_0010' => ['ER_API_023_DATE_AGREMENT_NB_0010', NonBlockingCode::AgreementEndDateNotInFuture];
        yield 'ER_AG_NB_00000' => ['ER_AG_NB_00000', NonBlockingCode::AgreementDateIncorrect];
        yield 'ER_AG_NB_00010' => ['ER_AG_NB_00010', NonBlockingCode::AgreementExpiring];
        yield 'ER_API_050_NB_0016' => ['ER_API_050_NB_0016', NonBlockingCode::ChildminderHourlyRateCapped];
        yield 'ER_API_051_NB_0016' => ['ER_API_051_NB_0016', NonBlockingCode::HomeCareHourlyRateCapped];
        yield 'ER_API_DECLA_NB_0010' => ['ER_API_DECLA_NB_0010', NonBlockingCode::SalaryGapWithPreviousDeclarations];
    }

    #[DataProvider('provideCodes')]
    public function testKnownCodeMapsTheWireValue(string $code, NonBlockingCode $expected): void
    {
        $message = NonBlockingMessage::of($code, null, null);

        static::assertSame($expected, $message->knownCode());
        static::assertSame($code, $expected->value);
    }

    public function testThereAreFourteenCodes(): void
    {
        static::assertCount(14, NonBlockingCode::cases());
    }

    public function testTheCodeIsTrimmed(): void
    {
        $message = NonBlockingMessage::of(" ER_API_020_NB_0000 \n", null, null);

        static::assertSame('ER_API_020_NB_0000', $message->code());
        static::assertSame(NonBlockingCode::EmployerNotAuthorised, $message->knownCode());
    }

    public function testAnUnknownCodeIsKeptRaw(): void
    {
        $message = NonBlockingMessage::of('ER_API_999_NB_0000', 'Court', 'Long');

        static::assertNull($message->knownCode());
        static::assertSame('ER_API_999_NB_0000', $message->code());
        static::assertSame('Court', $message->message());
        static::assertSame('Long', $message->description());
    }

    public function testMessageAndDescriptionAreOptional(): void
    {
        $message = NonBlockingMessage::of('ER_API_020_NB_0000', null, null);

        static::assertNull($message->message());
        static::assertNull($message->description());
    }

    public function testTheDescriptionIsMaskedInDebugOutput(): void
    {
        $dump = print_r(NonBlockingMessage::of('ER_API_020_NB_0000', 'Court', 'echoes JEAN DUPONT'), true);

        static::assertStringNotContainsString('DUPONT', $dump);
        static::assertStringContainsString('ER_API_020_NB_0000', $dump);
        static::assertStringContainsString('Court', $dump);
        static::assertStringContainsString('***', $dump);
        static::assertStringNotContainsString('***', print_r(NonBlockingMessage::of('ER_API_020_NB_0000', 'Court', null), true));
    }
}
