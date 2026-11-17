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
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Serializer\DeclarationResultSerializer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\Conversion\JsonConversion;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationResult;

/**
 * JSON column type of an optional {@see DeclarationResult}: a null result is stored as SQL NULL. The amounts and the
 * messages are personal data of the employee (ADR 0007, docs/doctrine.md), and a stored document the domain refuses is
 * never quoted in the conversion error.
 */
final class DeclarationResultType extends Type
{
    public const NAME = 'urssaf_paje_declaration_result';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return JsonConversion::toDatabase($value, DeclarationResult::class, self::class, DeclarationResultSerializer::toArray(...));
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DeclarationResult
    {
        return JsonConversion::decode($value, self::class, DeclarationResult::class, DeclarationResultSerializer::fromArray(...));
    }
}
