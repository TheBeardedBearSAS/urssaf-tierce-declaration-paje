<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\Conversion\UtcDateTimeConversion;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;

/**
 * Column type of {@see Instant}: a UTC date-time with second precision.
 */
final class InstantType extends Type
{
    public const NAME = 'urssaf_paje_instant';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getDateTimeTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!$value instanceof Instant) {
            throw InvalidType::new($value, self::class, [Instant::class, 'null']);
        }

        return UtcDateTimeConversion::format($value->toDateTimeImmutable(), $platform);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Instant
    {
        if ($value === null) {
            return null;
        }

        return Instant::fromDateTime(UtcDateTimeConversion::parse($value, self::class, $platform));
    }
}
