<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Serializer\EmployerVerificationSerializer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\Conversion\JsonConversion;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerVerification;

/**
 * JSON column type of an optional {@see EmployerVerification}: the last method 020 answer, kept with its raw online
 * account state. A null verification is stored as SQL NULL, so the nullable group never needs an embeddable (which
 * Doctrine cannot hydrate as null).
 */
final class EmployerVerificationType extends Type
{
    public const NAME = 'urssaf_paje_employer_verification';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return JsonConversion::toDatabase($value, EmployerVerification::class, self::class, EmployerVerificationSerializer::toArray(...));
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?EmployerVerification
    {
        return JsonConversion::decode($value, self::class, EmployerVerification::class, EmployerVerificationSerializer::fromArray(...));
    }
}
