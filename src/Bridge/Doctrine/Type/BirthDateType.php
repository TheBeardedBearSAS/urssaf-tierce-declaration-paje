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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;

/**
 * Column type of the employer birth date, in the employer account and in the mandate ({@see CalendarDate}, ADR 0007:
 * personal data): a DATE holding its "Y-m-d" string value. A type of its own, so that an application encrypts the birth
 * date alone (docs/doctrine.md) without touching the period and payment dates. A stored value the domain refuses, or a
 * foreign PHP value, is never quoted in the conversion error (ADR 0014).
 */
final class BirthDateType extends Type
{
    public const NAME = 'urssaf_paje_birth_date';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getDateTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return StringValueConversion::toDatabaseWithoutValue($value, CalendarDate::class, self::class, static fn(CalendarDate $value): string => $value->toString());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?CalendarDate
    {
        return StringValueConversion::toPhpWithoutValue($value, self::class, CalendarDate::fromString(...));
    }
}
