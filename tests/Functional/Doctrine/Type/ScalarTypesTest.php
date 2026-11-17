<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Type;

use DateTimeImmutable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\Conversion\UtcDateTimeConversion;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\InstantType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;

/**
 * Conversion scenarios of the instant column type.
 */
final class ScalarTypesTest extends TestCase
{
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        DoctrineIntegration::registerTypes();
        $this->platform = new SQLitePlatform();
    }

    public function testAnInstantIsStoredInUtcWithoutFractionAndReadBackAsTheSameInstant(): void
    {
        $type = new InstantType();
        $instant = Instant::fromIso8601('2022-02-01T16:40:00.789+01:00');

        static::assertSame('2022-02-01 15:40:00', $type->convertToDatabaseValue($instant, $this->platform));
        $read = $type->convertToPHPValue('2022-02-01 15:40:00', $this->platform);
        static::assertEquals($instant, $read);
        static::assertInstanceOf(Instant::class, $read);
        static::assertSame('UTC', $read->toDateTimeImmutable()->getTimezone()->getName());
        static::assertSame('DATETIME', $type->getSQLDeclaration([], $this->platform));
    }

    public function testAnInstantIsStoredAndReadInUtcWhateverTheDefaultTimeZone(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Pacific/Auckland');
        try {
            $type = new InstantType();
            $stored = $type->convertToDatabaseValue(Instant::fromIso8601('2022-02-01T16:40:00+01:00'), $this->platform);
            $read = $type->convertToPHPValue('2022-02-01 15:40:00', $this->platform);
        } finally {
            date_default_timezone_set($previous);
        }

        static::assertSame('2022-02-01 15:40:00', $stored);
        static::assertInstanceOf(Instant::class, $read);
        static::assertSame('2022-02-01T15:40:00Z', $read->toIso8601());
    }

    public function testADateTimeOfAnyZoneIsFormattedInUtc(): void
    {
        $value = new DateTimeImmutable('2022-02-01 20:40:00+05:00');

        static::assertSame('2022-02-01 15:40:00', UtcDateTimeConversion::format($value, $this->platform));
    }

    public function testTheInstantTypeRefusesWhatItDoesNotSupport(): void
    {
        $type = Type::getType(InstantType::NAME);

        static::assertNull($type->convertToDatabaseValue(null, $this->platform));
        static::assertNull($type->convertToPHPValue(null, $this->platform));
        $this->assertWrongPhpType($type, '2022-02-01', Instant::class);
        $this->assertWrongStoredType($type, 5, 'string');
        $this->assertInvalidStoredFormat($type, 'yesterday');
    }

    /**
     * @param class-string $expected
     */
    private function assertWrongPhpType(Type $type, mixed $value, string $expected): void
    {
        try {
            $type->convertToDatabaseValue($value, $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringEndsWith('types: ' . $expected . ', null.', $exception->getMessage());
        }
    }

    private function assertWrongStoredType(Type $type, mixed $stored, string $expected): void
    {
        try {
            $type->convertToPHPValue($stored, $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringEndsWith('types: ' . $expected . ', null.', $exception->getMessage());
        }
    }

    private function assertInvalidStoredFormat(Type $type, mixed $stored): void
    {
        try {
            $type->convertToPHPValue($stored, $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringContainsString('Expected format', $exception->getMessage());
        }
    }
}
