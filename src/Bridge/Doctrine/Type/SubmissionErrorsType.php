<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Serializer\SubmissionErrorsSerializer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\Conversion\JsonConversion;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\SubmissionError;

/**
 * JSON column type of the list of {@see SubmissionError} of a record (an empty list is "[]"). The messages and the
 * descriptions are personal data (ADR 0007), so a foreign PHP value or a stored document the domain refuses is never
 * quoted in the conversion error.
 */
final class SubmissionErrorsType extends Type
{
    public const NAME = 'urssaf_paje_submission_errors';

    private const EXPECTED = 'list<' . SubmissionError::class . '>';

    /** Placeholder quoted in place of a foreign PHP value in the conversion errors. */
    private const MASK = '***';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidType::new(self::MASK, self::class, [self::EXPECTED, 'null']);
        }
        // @mago-expect analysis:mixed-assignment The list comes from an untyped column value; each item is checked below.
        foreach ($value as $error) {
            if (!$error instanceof SubmissionError) {
                throw InvalidType::new(self::MASK, self::class, [self::EXPECTED, 'null']);
            }
        }

        /** @var list<SubmissionError> $value */
        return JsonConversion::encode(SubmissionErrorsSerializer::toArray($value));
    }

    /**
     * @return list<SubmissionError>|null
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
    {
        return JsonConversion::decode($value, self::class, self::EXPECTED, SubmissionErrorsSerializer::fromArray(...));
    }
}
