<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\Conversion;

use Closure;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;

/**
 * Conversion shared by the column types of string-backed value objects: stored as their string value, rebuilt through
 * the named constructor of the value object, so that an invalid stored value is reported instead of silently accepted.
 */
final class StringValueConversion
{
    /**
     * Placeholder quoted in place of a stored value that must not appear in a message (ADR 0007).
     */
    private const MASK = '***';

    /**
     * @template T of object
     *
     * @param class-string<T>    $class
     * @param class-string       $type     the column type, for the error message
     * @param Closure(T): string $toString the string value of the value object
     * @param-immediately-invoked-callable $toString
     */
    public static function toDatabase(mixed $value, string $class, string $type, Closure $toString): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!$value instanceof $class) {
            throw InvalidType::new($value, $type, [$class, 'null']);
        }

        return $toString($value);
    }

    /**
     * @template T of object
     *
     * @param class-string       $type    the column type, for the error message
     * @param Closure(string): T $factory the named constructor of the value object
     * @param-immediately-invoked-callable $factory
     *
     * @return T|null
     */
    public static function toPhp(mixed $value, string $type, Closure $factory): ?object
    {
        $stored = self::stored($value, $type, null);
        if ($stored === null) {
            return null;
        }
        try {
            return $factory($stored);
        } catch (InvalidValue $exception) {
            throw InvalidFormat::new($stored, $type, 'valid value', $exception);
        }
    }

    /**
     * Same contract as {@see self::toPhp()} for personal data: a stored value the value object refuses is not quoted in
     * the conversion error (DBAL puts it in the message, ADR 0007 forbids personal values in messages).
     *
     * @template T of object
     *
     * @param class-string       $type    the column type, for the error message
     * @param Closure(string): T $factory the named constructor of the value object
     * @param-immediately-invoked-callable $factory
     *
     * @return T|null
     */
    public static function toPhpWithoutValue(mixed $value, string $type, Closure $factory): ?object
    {
        $stored = self::stored($value, $type, self::MASK);
        if ($stored === null) {
            return null;
        }
        try {
            return $factory($stored);
        } catch (InvalidValue $exception) {
            throw InvalidFormat::new(self::MASK, $type, 'valid value', $exception);
        }
    }

    /**
     * Same contract as {@see self::toDatabase()} for personal data: a foreign PHP value is not quoted in the
     * conversion error (DBAL puts scalars in the message, ADR 0007 forbids personal values in messages).
     *
     * @template T of object
     *
     * @param class-string<T>    $class
     * @param class-string       $type     the column type, for the error message
     * @param Closure(T): string $toString the string value of the value object
     * @param-immediately-invoked-callable $toString
     */
    public static function toDatabaseWithoutValue(mixed $value, string $class, string $type, Closure $toString): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!$value instanceof $class) {
            throw InvalidType::new(self::MASK, $type, [$class, 'null']);
        }

        return $toString($value);
    }

    /**
     * @param class-string $type
     * @param string|null  $mask placeholder quoted instead of the value in the error message, null to quote the value
     */
    private static function stored(mixed $value, string $type, ?string $mask): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!\is_string($value)) {
            throw InvalidType::new($mask ?? $value, $type, ['string', 'null']);
        }

        return $value;
    }
}
