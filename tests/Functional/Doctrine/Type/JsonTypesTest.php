<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\DeclarationResultType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\EmployerVerificationType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\SubmissionErrorsType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationAmounts;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\SubmissionError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\WithholdingTax;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\OnlineAccountState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message\NonBlockingMessage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Rate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\RecordSamples;

/**
 * Conversion scenarios of the JSON column types: one method per scenario. Errors never quote a stored document nor a
 * foreign PHP value (the documents hold personal data, ADR 0007).
 *
 * @mago-expect lint:too-many-methods
 */
final class JsonTypesTest extends TestCase
{
    private const ERRORS_TYPE = 'list<' . SubmissionError::class . '>';

    private const AMOUNTS = '{"contributions":1000,"coveredByFamilyBenefits":200,"employerShare":300,"withholding":{"taxableNetSalary":40000,"rate":"5.5","taxWithheld":2200,"netPayableAfterTax":37800},"cmgRemuneration":400,"payableToEmployee":500,"payableByPaje":600,"debitedByPaje":700,"plannedDebitDate":"2026-11-25","plannedPaymentDate":"2026-11-30"}';

    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        DoctrineIntegration::registerTypes();
        $this->platform = new SQLitePlatform();
    }

    /**
     * @return iterable<string, array{string}> case names of {@see self::document()}
     */
    public static function documents(): iterable
    {
        yield 'verification of a documented state' => ['verification'];
        yield 'verification of an unknown raw state' => ['verification-unknown'];
        yield 'errors with and without message and description' => ['errors'];
        yield 'no error' => ['no-errors'];
        yield 'full result' => ['result-full'];
        yield 'minimal result' => ['result-minimal'];
        yield 'result without amounts' => ['result-no-amounts'];
        yield 'result with a rate and no other amount' => ['result-rate'];
        yield 'result with amounts and no withholding' => ['result-no-withholding'];
    }

    /**
     * @return array{string, object|list<SubmissionError>, string, string} type name, value, expected JSON, description of the PHP type
     *
     * @throws InvalidValue
     */
    private static function document(string $case): array
    {
        return match ($case) {
            'verification' => [
                EmployerVerificationType::NAME,
                EmployerVerification::of(true, OnlineAccountState::of('Actif')),
                '{"authorised":true,"state":"Actif"}',
                EmployerVerification::class,
            ],
            'verification-unknown' => [
                EmployerVerificationType::NAME,
                EmployerVerification::of(false, OnlineAccountState::of('Etat-futur')),
                '{"authorised":false,"state":"Etat-futur"}',
                EmployerVerification::class,
            ],
            'errors' => [
                SubmissionErrorsType::NAME,
                [
                    SubmissionError::of('ER_API_050_0070', 'duplicate_declaration', 'First message.', 'First description.'),
                    SubmissionError::of('ER_API_050_0080', 'business_rule', null, null),
                ],
                '[{"code":"ER_API_050_0070","kind":"duplicate_declaration","message":"First message.","description":"First description."},{"code":"ER_API_050_0080","kind":"business_rule","message":null,"description":null}]',
                self::ERRORS_TYPE,
            ],
            'no-errors' => [SubmissionErrorsType::NAME, [], '[]', self::ERRORS_TYPE],
            'result-full' => [
                DeclarationResultType::NAME,
                DeclarationResult::of(
                    'REF-001',
                    DeclarationAmounts::of(
                        Amount::fromCents(1_000),
                        Amount::fromCents(200),
                        Amount::fromCents(300),
                        WithholdingTax::of(Amount::fromCents(40_000), Rate::fromString('5.5'), Amount::fromCents(2_200), Amount::fromCents(37_800)),
                        Amount::fromCents(400),
                        Amount::fromCents(500),
                        Amount::fromCents(600),
                        Amount::fromCents(700),
                        CalendarDate::fromString('2026-11-25'),
                        CalendarDate::fromString('2026-11-30'),
                    ),
                    true,
                    CalendarDate::fromString('2026-11-20'),
                    [],
                    [NonBlockingMessage::of('ER_API_DECLA_00007', 'Message', 'Description'), NonBlockingMessage::of('W2', null, null)],
                ),
                '{"documentReference":"REF-001","amounts":' . self::AMOUNTS . ',"pajemploiPlus":true,"transferDate":"2026-11-20","messages":[{"code":"ER_API_DECLA_00007","message":"Message","description":"Description"},{"code":"W2","message":null,"description":null}]}',
                DeclarationResult::class,
            ],
            'result-minimal' => [
                DeclarationResultType::NAME,
                DeclarationResult::of(null, null, false, null, [], []),
                '{"documentReference":null,"amounts":null,"pajemploiPlus":false,"transferDate":null,"messages":[]}',
                DeclarationResult::class,
            ],
            'result-no-amounts' => [
                DeclarationResultType::NAME,
                DeclarationResult::of('REF-002', null, true, CalendarDate::fromString('2026-12-01'), [], []),
                '{"documentReference":"REF-002","amounts":null,"pajemploiPlus":true,"transferDate":"2026-12-01","messages":[]}',
                DeclarationResult::class,
            ],
            'result-no-withholding' => [
                DeclarationResultType::NAME,
                DeclarationResult::of(
                    null,
                    DeclarationAmounts::of(null, null, null, WithholdingTax::of(null, null, null, null), null, null, null, null, null, null),
                    false,
                    null,
                    [],
                    [],
                ),
                '{"documentReference":null,"amounts":{"contributions":null,"coveredByFamilyBenefits":null,"employerShare":null,"withholding":{"taxableNetSalary":null,"rate":null,"taxWithheld":null,"netPayableAfterTax":null},"cmgRemuneration":null,"payableToEmployee":null,"payableByPaje":null,"debitedByPaje":null,"plannedDebitDate":null,"plannedPaymentDate":null},"pajemploiPlus":false,"transferDate":null,"messages":[]}',
                DeclarationResult::class,
            ],
            default => [
                DeclarationResultType::NAME,
                DeclarationResult::of(
                    null,
                    DeclarationAmounts::of(null, null, null, WithholdingTax::of(null, Rate::fromString('0'), null, null), null, null, null, null, null, null),
                    false,
                    null,
                    [],
                    [],
                ),
                '{"documentReference":null,"amounts":{"contributions":null,"coveredByFamilyBenefits":null,"employerShare":null,"withholding":{"taxableNetSalary":null,"rate":"0","taxWithheld":null,"netPayableAfterTax":null},"cmgRemuneration":null,"payableToEmployee":null,"payableByPaje":null,"debitedByPaje":null,"plannedDebitDate":null,"plannedPaymentDate":null},"pajemploiPlus":false,"transferDate":null,"messages":[]}',
                DeclarationResult::class,
            ],
        };
    }

    #[DataProvider('documents')]
    public function testItStoresAnExplicitDocumentAndRebuildsTheValue(string $case): void
    {
        [$name, $value, $json] = self::document($case);
        $type = Type::getType($name);

        static::assertSame($json, $type->convertToDatabaseValue($value, $this->platform));
        static::assertEquals($value, $type->convertToPHPValue($json, $this->platform));
    }

    public function testTheRawStateOfAVerificationSurvivesTheRoundTrip(): void
    {
        $type = new EmployerVerificationType();
        $verification = EmployerVerification::of(true, OnlineAccountState::of('Etat-futur'));
        $json = $type->convertToDatabaseValue($verification, $this->platform);

        static::assertIsString($json);
        $read = $type->convertToPHPValue($json, $this->platform);

        static::assertInstanceOf(EmployerVerification::class, $read);
        static::assertSame('Etat-futur', $read->state()->raw());
        static::assertNull($read->state()->documented());
        static::assertTrue($read->authorised());
    }

    public function testTheChildChecksOfAResultAreNeverStored(): void
    {
        $result = RecordSamples::result('REF-001');
        static::assertNotSame([], $result->childChecks());

        $json = (new DeclarationResultType())->convertToDatabaseValue($result, $this->platform);

        static::assertIsString($json);
        static::assertStringNotContainsString('JEAN', $json);
        static::assertStringNotContainsString('child', strtolower($json));
        $read = (new DeclarationResultType())->convertToPHPValue($json, $this->platform);
        static::assertInstanceOf(DeclarationResult::class, $read);
        static::assertSame([], $read->childChecks());
        static::assertSame('REF-001', $read->documentReference());
        static::assertEquals(DeclarationResult::of($result->documentReference(), $result->amounts(), $result->isPajemploiPlus(), $result->transferDate(), [], $result->messages()), $read);
    }

    #[DataProvider('documents')]
    public function testNullStaysNull(string $case): void
    {
        [$name] = self::document($case);
        $type = Type::getType($name);

        static::assertNull($type->convertToDatabaseValue(null, $this->platform));
        static::assertNull($type->convertToPHPValue(null, $this->platform));
    }

    #[DataProvider('documents')]
    public function testItRefusesAForeignPhpValueWithoutQuotingIt(string $case): void
    {
        [$name, , , $expected] = self::document($case);

        try {
            Type::getType($name)->convertToDatabaseValue('SECRET-foreign', $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringEndsWith('types: ' . $expected . ', null.', $exception->getMessage());
            static::assertStringContainsString('***', $exception->getMessage());
            static::assertStringNotContainsString('SECRET', $exception->getMessage());
        }
    }

    #[DataProvider('documents')]
    public function testItRefusesAStoredValueThatIsNotValidJsonObjectOrArray(string $case): void
    {
        [$name] = self::document($case);
        $messages = [];

        foreach ([12, '{not json', '12', '"text"'] as $stored) {
            try {
                Type::getType($name)->convertToPHPValue($stored, $this->platform);
                static::fail('Expected a conversion failure.');
            } catch (ConversionException $exception) {
                $messages[] = $exception->getMessage();
            }
        }

        static::assertStringEndsWith('types: string, null.', $messages[0]);
        static::assertStringContainsString('Expected format "JSON"', $messages[1]);
        static::assertStringContainsString('Expected format "JSON object or array"', $messages[2]);
        static::assertStringContainsString('Expected format "JSON object or array"', $messages[3]);
        static::assertStringNotContainsString('{not json', $messages[1]);
        static::assertStringContainsString('database value "***"', $messages[1]);
        static::assertStringContainsString('database value "***"', $messages[2]);
        static::assertStringContainsString('database value "***"', $messages[3]);
    }

    public function testAJsonDocumentIsDeclaredAsAJsonColumn(): void
    {
        foreach ([EmployerVerificationType::NAME, SubmissionErrorsType::NAME, DeclarationResultType::NAME] as $name) {
            static::assertSame(
                $this->platform->getJsonTypeDeclarationSQL([]),
                Type::getType($name)->getSQLDeclaration([], $this->platform),
            );
        }
    }

    public function testItRefusesAListThatIsNotAListOfSubmissionErrors(): void
    {
        $type = Type::getType(SubmissionErrorsType::NAME);
        $error = SubmissionError::of('ERR_A', 'kind', 'SECRET-message', 'SECRET-description');

        foreach ([['a' => $error], [$error, 'SECRET-text'], [1 => $error]] as $value) {
            try {
                $type->convertToDatabaseValue($value, $this->platform);
                static::fail('Expected a conversion failure.');
            } catch (ConversionException $exception) {
                static::assertStringEndsWith('types: ' . self::ERRORS_TYPE . ', null.', $exception->getMessage());
                static::assertStringNotContainsString('SECRET', $exception->getMessage());
            }
        }
    }

    public function testStoredContentThatTheDomainRefusesIsReportedWithoutEchoingIt(): void
    {
        $cases = [
            'errors, empty code' => [SubmissionErrorsType::NAME, '[{"code":"","kind":"k","message":"SECRET-message","description":"SECRET-description"}]'],
            'errors, empty kind' => [SubmissionErrorsType::NAME, '[{"code":"ERR","kind":" ","message":"SECRET-message","description":null}]'],
            'result, transfer date' => [DeclarationResultType::NAME, '{"documentReference":"SECRET-reference","amounts":null,"pajemploiPlus":true,"transferDate":"SECRET-date","messages":[]}'],
            'result, planned debit date' => [DeclarationResultType::NAME, '{"documentReference":null,"amounts":' . str_replace('"plannedDebitDate":"2026-11-25"', '"plannedDebitDate":"2026-13-45"', self::AMOUNTS) . ',"pajemploiPlus":true,"transferDate":null,"messages":[]}'],
            'result, planned payment date' => [DeclarationResultType::NAME, '{"documentReference":null,"amounts":' . str_replace('"plannedPaymentDate":"2026-11-30"', '"plannedPaymentDate":"SECRET-date"', self::AMOUNTS) . ',"pajemploiPlus":true,"transferDate":null,"messages":[]}'],
            'result, rate' => [DeclarationResultType::NAME, '{"documentReference":null,"amounts":' . str_replace('"rate":"5.5"', '"rate":"SECRET-rate"', self::AMOUNTS) . ',"pajemploiPlus":true,"transferDate":null,"messages":[]}'],
        ];

        foreach ($cases as $label => [$name, $stored]) {
            try {
                Type::getType($name)->convertToPHPValue($stored, $this->platform);
                static::fail('Expected a conversion failure for ' . $label);
            } catch (ConversionException $exception) {
                static::assertInstanceOf(InvalidValue::class, $exception->getPrevious(), $label);
                static::assertStringContainsString('database value "***"', $exception->getMessage(), $label);
                static::assertStringNotContainsString('SECRET', $exception->getMessage(), $label);
                static::assertStringNotContainsString('2026-13-45', $exception->getMessage(), $label);
            }
        }
    }
}
