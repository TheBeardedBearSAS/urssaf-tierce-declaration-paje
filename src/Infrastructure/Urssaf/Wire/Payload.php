<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Typed, defensive view over a decoded JSON document. This is the only place where untyped wire data is read:
 * every accessor either returns a well-typed value or throws {@see UnexpectedUrssafResponse}. Exception reasons name
 * the field, never its value (ADR 0007).
 *
 * One accessor per JSON shape read from URSSAF, hence the method count and complexity.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 */
final readonly class Payload
{
    private const INTEGER_DIGITS = '/^-?[1-9]\d*$/D';

    /**
     * @param array<array-key, array<array-key, mixed>|scalar|null> $data
     */
    private function __construct(
        #[SensitiveParameter]
        private array $data,
        private bool $jsonObject,
    ) {}

    /**
     * @throws UnexpectedUrssafResponse when the decoded JSON is neither an object nor an array
     */
    public static function fromDecoded(#[SensitiveParameter] mixed $decoded): self
    {
        if (!\is_array($decoded)) {
            throw UnexpectedUrssafResponse::invalidPayload('body is neither a JSON object nor a JSON array');
        }

        return self::wrap($decoded);
    }

    /**
     * Keys only, never a value: a payload holds personal data (ADR 0007).
     *
     * @return array<array-key, string>
     */
    public function __debugInfo(): array
    {
        return array_fill_keys(array_keys($this->data), '***');
    }

    /**
     * No data at all: the empty body of a 2xx answer (ADR 0002). It is not a JSON object.
     */
    public static function empty(): self
    {
        return new self([], false);
    }

    /**
     * The JSON object `{}`: no data either, but a document that is an object (every field of most responses is
     * optional). `json_decode()` cannot tell it from `[]`, so only the decoder knows.
     */
    public static function emptyObject(): self
    {
        return new self([], true);
    }

    /**
     * @param array<array-key, mixed> $data decoded JSON object or array
     */
    private static function wrap(array $data): self
    {
        /** @var array<array-key, array<array-key, mixed>|scalar|null> $typed */
        $typed = $data;

        return new self($typed, !array_is_list($typed));
    }

    /**
     * Guards the entry point of a response mapper: an empty body, `[]` and any top-level list are not a JSON object,
     * and a 2xx answer that is none must never read as a success whose fields are all absent (ADR 0002, ADR 0003).
     * `{}` is accepted.
     *
     * @throws UnexpectedUrssafResponse when this payload is not a JSON object
     */
    public function requireJsonObject(): void
    {
        if (!$this->jsonObject) {
            throw UnexpectedUrssafResponse::invalidPayload('body is not a JSON object');
        }
    }

    /**
     * True for an empty body, `{}` and `[]`.
     */
    public function isEmpty(): bool
    {
        return $this->data === [];
    }

    public function isList(): bool
    {
        return array_is_list($this->data);
    }

    /**
     * True when the key is present, whatever its value (even null).
     */
    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->data);
    }

    /**
     * Lenient string value of a key: null when absent, null or of another type. Used to read error bodies and
     * non-blocking messages, which must never fail a call.
     */
    public function string(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return \is_string($value) ? $value : null;
    }

    /**
     * @throws UnexpectedUrssafResponse when present but not a string
     */
    public function optionalString(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        if ($value === null || \is_string($value)) {
            return $value;
        }

        throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is not a string', $key));
    }

    /**
     * @return non-empty-string
     *
     * @throws UnexpectedUrssafResponse when the key is absent or its value is not a non-empty string
     */
    public function requireString(string $key): string
    {
        $value = $this->string($key) ?? '';

        if ($value === '') {
            throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is missing or empty', $key));
        }

        return $value;
    }

    /**
     * @throws UnexpectedUrssafResponse when present but not a boolean
     */
    public function optionalBool(string $key): ?bool
    {
        $value = $this->data[$key] ?? null;

        if ($value === null || \is_bool($value)) {
            return $value;
        }

        throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is not a boolean', $key));
    }

    /**
     * @throws UnexpectedUrssafResponse when the key is absent or its value is not a boolean
     */
    public function requireBool(string $key): bool
    {
        $value = $this->data[$key] ?? null;

        if (!\is_bool($value)) {
            throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is missing or not a boolean', $key));
        }

        return $value;
    }

    /**
     * @throws UnexpectedUrssafResponse when present but not an integer
     */
    public function int(string $key): ?int
    {
        $value = $this->data[$key] ?? null;

        if ($value === null || \is_int($value)) {
            return $value;
        }

        throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is not an integer', $key));
    }

    /**
     * @throws UnexpectedUrssafResponse when the key is absent or its value is not an integer
     */
    public function requireInt(string $key): int
    {
        $value = $this->data[$key] ?? null;

        if (!\is_int($value)) {
            throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is missing or not an integer', $key));
        }

        return $value;
    }

    /**
     * JSON number of a key. Null when absent.
     *
     * @throws UnexpectedUrssafResponse when present but not a number
     */
    public function number(string $key): int|float|null
    {
        $value = $this->data[$key] ?? null;

        if ($value === null || \is_int($value) || \is_float($value)) {
            return $value;
        }

        throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is not a number', $key));
    }

    /**
     * @throws UnexpectedUrssafResponse when the key is absent or its value is not a number
     */
    public function requireNumber(string $key): int|float
    {
        $value = $this->data[$key] ?? null;

        if (!\is_int($value) && !\is_float($value)) {
            throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is missing or not a number', $key));
        }

        return $value;
    }

    /**
     * JSON number that may exceed the integer range: such an integer is decoded as its digit string
     * (`JSON_BIGINT_AS_STRING`), which {@see AmountEncoding::decode()} accepts. A digit string is accepted only when
     * it does not fit an integer (the only case that comes from `JSON_BIGINT_AS_STRING`): `"500"` is a JSON string, not
     * a number. Null when absent.
     *
     * @throws UnexpectedUrssafResponse when present but neither a number nor a digit string
     */
    public function numeric(string $key): int|float|string|null
    {
        $value = $this->data[$key] ?? null;

        if ($value === null || \is_int($value) || \is_float($value)) {
            return $value;
        }

        if (\is_string($value) && preg_match(self::INTEGER_DIGITS, $value) === 1 && (string) (int) $value !== $value) {
            return $value;
        }

        throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is not a number', $key));
    }

    /**
     * @throws UnexpectedUrssafResponse when the key is absent or its value is neither a number nor a digit string
     */
    public function requireNumeric(string $key): int|float|string
    {
        return $this->numeric($key) ?? throw UnexpectedUrssafResponse::invalidPayload(
            \sprintf('field "%s" is missing or not a number', $key),
        );
    }

    /**
     * Nested JSON object. Null when absent.
     *
     * @throws UnexpectedUrssafResponse when present but not an object
     */
    public function object(string $key): ?self
    {
        $value = $this->data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (!\is_array($value) || self::isFilledList($value)) {
            throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is not an object', $key));
        }

        return self::wrap($value);
    }

    /**
     * @throws UnexpectedUrssafResponse when the key is absent or its value is not an object
     */
    public function requireObject(string $key): self
    {
        $value = $this->data[$key] ?? null;

        if (!\is_array($value) || self::isFilledList($value)) {
            throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is missing or not an object', $key));
        }

        return self::wrap($value);
    }

    /**
     * Nested list of objects. Empty when absent.
     *
     * @return list<self>
     *
     * @throws UnexpectedUrssafResponse when present but not a list of objects
     */
    public function items(string $key): array
    {
        $value = $this->data[$key] ?? null;

        if ($value === null) {
            return [];
        }

        if (!\is_array($value)) {
            throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is not a JSON array', $key));
        }

        return self::entriesOf($value, \sprintf('field "%s"', $key));
    }

    /**
     * Elements of a top-level JSON array, each of which must be an object.
     *
     * @return list<self>
     *
     * @throws UnexpectedUrssafResponse when this payload is not a list of objects
     */
    public function entries(): array
    {
        return self::entriesOf($this->data, 'body');
    }

    /**
     * Lenient counterpart of {@see self::entries()}: elements that are not objects are skipped and a payload that is
     * not a list gives no entry. Used to read error bodies, which must never fail a call.
     *
     * @return list<self>
     */
    public function lenientEntries(): array
    {
        return self::lenientEntriesOf($this->data);
    }

    /**
     * Lenient counterpart of {@see self::items()}: empty when absent, null or not a list, non-object elements skipped.
     *
     * @return list<self>
     */
    public function lenientItems(string $key): array
    {
        $value = $this->data[$key] ?? null;

        return \is_array($value) ? self::lenientEntriesOf($value) : [];
    }

    /**
     * A non-empty list is a JSON array, never an object. `[]` stays accepted: some serialisers emit an empty object
     * that way. Known limit: `json_decode()` turns an object with keys "0", "1"... into a list, which is then refused.
     *
     * @param array<array-key, mixed> $value
     */
    private static function isFilledList(array $value): bool
    {
        return $value !== [] && array_is_list($value);
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<self>
     */
    private static function lenientEntriesOf(array $data): array
    {
        if (!array_is_list($data)) {
            return [];
        }

        return array_values(array_map(self::wrap(...), array_filter($data, \is_array(...))));
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<self>
     */
    private static function entriesOf(array $data, string $subject): array
    {
        if (!array_is_list($data)) {
            throw UnexpectedUrssafResponse::invalidPayload(\sprintf('%s is not a JSON array', $subject));
        }

        return array_map(
            static fn(mixed $entry): self => \is_array($entry)
                ? self::wrap($entry)
                : throw UnexpectedUrssafResponse::invalidPayload(\sprintf('%s is not a JSON array of objects', $subject)),
            $data,
        );
    }
}
