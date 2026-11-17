<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\Conversion;

use Closure;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use JsonException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;

/**
 * Conversion shared by the JSON column types: an explicit, hand-written serializer (see the Serializer namespace) turns
 * the domain value into an array and back, rebuilding it through its named constructors.
 *
 * The stored documents may hold personal values (URSSAF descriptions, amounts of the employee, ADR 0007): every
 * conversion error is built with a mask instead of the stored document.
 */
final class JsonConversion
{
    private const DEPTH = 512;

    /**
     * Placeholder quoted in place of the stored document in the conversion errors.
     */
    private const MASK = '***';

    /**
     * Stores an optional value as its document; a foreign PHP value is refused without being quoted in the error.
     *
     * @template T of object
     *
     * @param class-string<T>                     $class
     * @param class-string                        $type      the column type, for the error message
     * @param Closure(T): array<array-key, mixed> $serialize the serializer
     * @param-immediately-invoked-callable $serialize
     */
    public static function toDatabase(mixed $value, string $class, string $type, Closure $serialize): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!$value instanceof $class) {
            throw InvalidType::new(self::MASK, $type, [$class, 'null']);
        }

        return self::encode($serialize($value));
    }

    /**
     * @param array<array-key, mixed> $document
     */
    public static function encode(array $document): string
    {
        return json_encode($document, \JSON_THROW_ON_ERROR);
    }

    /**
     * @template T
     *
     * @param class-string                        $type     the column type, for the error message
     * @param string                              $expected description of the rebuilt value, for the error message
     * @param Closure(array<array-key, mixed>): T $rebuild  the serializer
     * @param-immediately-invoked-callable $rebuild
     *
     * @return T|null
     */
    public static function decode(mixed $value, string $type, string $expected, Closure $rebuild): mixed
    {
        if ($value === null) {
            return null;
        }
        if (!\is_string($value)) {
            throw InvalidType::new(self::MASK, $type, ['string', 'null']);
        }
        try {
            // @mago-expect analysis:mixed-assignment The decoded JSON is untyped; it is checked to be an array below.
            $document = json_decode($value, true, self::DEPTH, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidFormat::new(self::MASK, $type, 'JSON', $exception);
        }
        if (!\is_array($document)) {
            throw InvalidFormat::new(self::MASK, $type, 'JSON object or array');
        }
        try {
            return $rebuild($document);
        } catch (InvalidValue $exception) {
            throw InvalidFormat::new(self::MASK, $type, $expected, $exception);
        }
    }
}
