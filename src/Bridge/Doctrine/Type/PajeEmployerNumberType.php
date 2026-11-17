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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;

/**
 * Column type of {@see PajeEmployerNumber} (the employer Pajemploi number, a credential-like identifier): a VARCHAR
 * holding its string value. A stored value the domain refuses, or a foreign PHP value, is never quoted in the
 * conversion error (ADR 0007, ADR 0014).
 */
final class PajeEmployerNumberType extends Type
{
    public const NAME = 'urssaf_paje_employer_number';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return StringValueConversion::toDatabaseWithoutValue($value, PajeEmployerNumber::class, self::class, static fn(PajeEmployerNumber $value): string => $value->value());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PajeEmployerNumber
    {
        return StringValueConversion::toPhpWithoutValue($value, self::class, PajeEmployerNumber::of(...));
    }
}
