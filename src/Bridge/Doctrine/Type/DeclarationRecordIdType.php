<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\Conversion\StringValueConversion;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;

/**
 * Column type of {@see DeclarationRecordId}: a VARCHAR holding its string value.
 */
final class DeclarationRecordIdType extends Type
{
    public const NAME = 'urssaf_paje_declaration_record_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return StringValueConversion::toDatabase($value, DeclarationRecordId::class, self::class, static fn(DeclarationRecordId $value): string => $value->value());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DeclarationRecordId
    {
        return StringValueConversion::toPhp($value, self::class, DeclarationRecordId::fromString(...));
    }
}
