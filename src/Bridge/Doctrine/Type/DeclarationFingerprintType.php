<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\Conversion\StringValueConversion;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationFingerprint;

/**
 * Column type of {@see DeclarationFingerprint}: a VARCHAR holding its string value.
 */
final class DeclarationFingerprintType extends Type
{
    public const NAME = 'urssaf_paje_declaration_fingerprint';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return StringValueConversion::toDatabase($value, DeclarationFingerprint::class, self::class, static fn(DeclarationFingerprint $value): string => $value->value());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DeclarationFingerprint
    {
        return StringValueConversion::toPhp($value, self::class, DeclarationFingerprint::fromString(...));
    }
}
