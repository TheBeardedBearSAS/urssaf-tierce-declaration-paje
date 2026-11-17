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
use Stringable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\BirthDateType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\CalendarDateType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\DeclarationFingerprintType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\DeclarationRecordIdType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\EmployerAccountIdType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\ExternalReferenceType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\MandateIdType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\PajeEmployeeNumberType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\PajeEmployerNumberType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\TenantIdType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationFingerprint;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\PajeEmployeeNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use Throwable;

/**
 * Conversion scenarios of the string-backed and date column types: the employer and employee numbers and the birth date
 * are personal data and are never quoted in a conversion error (ADR 0007, ADR 0014).
 *
 * @mago-expect lint:too-many-methods
 */
final class StringValueTypesTest extends TestCase
{
    private const FINGERPRINT = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    /** Types whose stored and PHP values are masked in the conversion errors. */
    private const MASKED = [PajeEmployerNumberType::NAME, PajeEmployeeNumberType::NAME, BirthDateType::NAME];

    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        DoctrineIntegration::registerTypes();
        $this->platform = new SQLitePlatform();
    }

    /**
     * @return iterable<string, array{string, string, string}> type name, stored value, an invalid stored value
     */
    public static function types(): iterable
    {
        yield 'tenant id' => [TenantIdType::NAME, 'tenant-1', '   '];
        yield 'external reference' => [ExternalReferenceType::NAME, 'employer-1', '   '];
        yield 'employer account id' => [EmployerAccountIdType::NAME, 'account-1', '   '];
        yield 'mandate id' => [MandateIdType::NAME, 'mandate-1', '   '];
        yield 'declaration record id' => [DeclarationRecordIdType::NAME, 'record-1', '   '];
        yield 'employer number' => [PajeEmployerNumberType::NAME, 'Y1869005780009', 'Y186900578000'];
        yield 'employee number' => [PajeEmployeeNumberType::NAME, 'Y2926822480004', 'Y292682248000'];
        yield 'declaration fingerprint' => [DeclarationFingerprintType::NAME, self::FINGERPRINT, 'not-a-fingerprint'];
        yield 'calendar date' => [CalendarDateType::NAME, '2026-10-31', '2026-13-45'];
        yield 'birth date' => [BirthDateType::NAME, '1975-01-30', '1975-13-45'];
    }

    #[DataProvider('types')]
    public function testItStoresTheValueAndRebuildsTheValueObject(string $name, string $stored): void
    {
        $type = Type::getType($name);
        $value = self::valueObject($name, $stored);

        static::assertSame($stored, $type->convertToDatabaseValue($value, $this->platform));
        static::assertEquals($value, $type->convertToPHPValue($stored, $this->platform));
    }

    #[DataProvider('types')]
    public function testNullStaysNull(string $name): void
    {
        $type = Type::getType($name);

        static::assertNull($type->convertToDatabaseValue(null, $this->platform));
        static::assertNull($type->convertToPHPValue(null, $this->platform));
    }

    #[DataProvider('types')]
    public function testItRefusesAForeignPhpValue(string $name, string $stored): void
    {
        $foreign = new class implements Stringable {
            public function __toString(): string
            {
                return 'foreign';
            }
        };

        try {
            Type::getType($name)->convertToDatabaseValue($foreign, $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringEndsWith('types: ' . self::valueObject($name, $stored)::class . ', null.', $exception->getMessage());
        }
    }

    #[DataProvider('types')]
    public function testItNeverQuotesAForeignPhpStringOfAMaskedType(string $name, string $stored): void
    {
        try {
            Type::getType($name)->convertToDatabaseValue($stored, $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertSame(\in_array($name, self::MASKED, true), str_contains($exception->getMessage(), '***'));
            static::assertSame(!\in_array($name, self::MASKED, true), str_contains($exception->getMessage(), $stored));
        }
    }

    #[DataProvider('types')]
    public function testItRefusesAStoredValueThatIsNotAString(string $name): void
    {
        try {
            Type::getType($name)->convertToPHPValue(12, $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringEndsWith('types: string, null.', $exception->getMessage());
            static::assertSame(\in_array($name, self::MASKED, true), str_contains($exception->getMessage(), '***'));
        }
    }

    #[DataProvider('types')]
    public function testItReportsAStoredValueTheValueObjectRefuses(string $name, string $stored, string $invalid): void
    {
        try {
            Type::getType($name)->convertToPHPValue($invalid, $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringContainsString(Type::getType($name)::class, $exception->getMessage());
            static::assertInstanceOf(InvalidValue::class, $exception->getPrevious());
            static::assertSame(
                !\in_array($name, self::MASKED, true),
                str_contains($exception->getMessage(), $invalid),
            );
            static::assertSame(\in_array($name, self::MASKED, true), str_contains($exception->getMessage(), 'database value "***"'));
        }
    }

    public function testAMaskedTypeNeverQuotesWhatItCannotConvert(): void
    {
        $stored = ['employer number' => 'Y1869005780009', 'employee number' => 'Y2926822480004', 'birth date' => '1975-01-30'];
        $names = ['employer number' => PajeEmployerNumberType::NAME, 'employee number' => PajeEmployeeNumberType::NAME, 'birth date' => BirthDateType::NAME];
        static::assertSame(self::MASKED, array_values($names));

        foreach ($names as $label => $name) {
            foreach ([12, [$stored[$label]], 1_975] as $foreign) {
                try {
                    Type::getType($name)->convertToPHPValue($foreign, $this->platform);
                    static::fail('Expected a conversion failure for ' . $label);
                } catch (ConversionException $exception) {
                    static::assertStringContainsString('***', $exception->getMessage());
                    static::assertStringNotContainsString($stored[$label], $exception->getMessage());
                    static::assertStringNotContainsString('1975', $exception->getMessage());
                }
            }
        }
    }

    public function testStringTypesUseTheColumnLengthAndDatesUseTheDateType(): void
    {
        static::assertSame('VARCHAR(64)', Type::getType(TenantIdType::NAME)->getSQLDeclaration(['length' => 64], $this->platform));
        static::assertSame('VARCHAR(14)', Type::getType(PajeEmployerNumberType::NAME)->getSQLDeclaration(['length' => 14], $this->platform));
        static::assertSame('VARCHAR(14)', Type::getType(PajeEmployeeNumberType::NAME)->getSQLDeclaration(['length' => 14], $this->platform));
        static::assertSame('DATE', Type::getType(CalendarDateType::NAME)->getSQLDeclaration([], $this->platform));
        static::assertSame('DATE', Type::getType(BirthDateType::NAME)->getSQLDeclaration([], $this->platform));
    }

    public function testTheErrorsOfTheOtherTypesQuoteTheirStoredValue(): void
    {
        try {
            Type::getType(TenantIdType::NAME)->convertToPHPValue('   ', $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertInstanceOf(Throwable::class, $exception->getPrevious());
            static::assertStringContainsString('"   "', $exception->getMessage());
        }
    }

    /**
     * @throws InvalidValue
     */
    private static function valueObject(string $typeName, string $stored): object
    {
        return match ($typeName) {
            TenantIdType::NAME => TenantId::fromString($stored),
            ExternalReferenceType::NAME => ExternalReference::fromString($stored),
            EmployerAccountIdType::NAME => EmployerAccountId::fromString($stored),
            MandateIdType::NAME => MandateId::fromString($stored),
            DeclarationRecordIdType::NAME => DeclarationRecordId::fromString($stored),
            PajeEmployerNumberType::NAME => PajeEmployerNumber::of($stored),
            PajeEmployeeNumberType::NAME => PajeEmployeeNumber::of($stored),
            DeclarationFingerprintType::NAME => DeclarationFingerprint::fromString($stored),
            default => CalendarDate::fromString($stored),
        };
    }
}
