<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\Conversion\StringValueConversion;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;

/**
 * Column type of {@see ExternalReference}: a VARCHAR holding its string value.
 */
final class ExternalReferenceType extends Type
{
    public const NAME = 'urssaf_paje_external_reference';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return StringValueConversion::toDatabase($value, ExternalReference::class, self::class, static fn(ExternalReference $value): string => $value->value());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ExternalReference
    {
        return StringValueConversion::toPhp($value, self::class, ExternalReference::fromString(...));
    }
}
