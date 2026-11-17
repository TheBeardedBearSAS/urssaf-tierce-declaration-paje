<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;

/**
 * A validated optional HTTP request header (ADR 0002): an RFC 9110 token name that is not one of the headers the
 * gateway sets itself, and a visible-ASCII value. Violation messages never contain the value.
 */
final readonly class HttpHeader
{
    public const NAME_INVALID = 'http_header.name_invalid';
    public const NAME_RESERVED = 'http_header.name_reserved';
    public const VALUE_INVALID = 'http_header.value_invalid';

    private const NAME_PATTERN = '/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/D';
    private const VALUE_PATTERN = '/^[\x21-\x7E](?:[\x20-\x7E]*[\x21-\x7E])?$/D';

    /**
     * Headers written by the gateway itself (lower case).
     */
    private const RESERVED_NAMES = ['authorization', 'accept', 'content-type', 'content-length', 'host', 'user-agent'];

    private function __construct(
        private string $name,
        #[SensitiveParameter]
        private string $value,
    ) {}

    /**
     * @throws InvalidValue
     */
    public static function of(string $name, #[SensitiveParameter] string $value): self
    {
        self::validName($name);

        if (1 !== preg_match(self::VALUE_PATTERN, $value)) {
            throw InvalidValue::because(self::VALUE_INVALID, 'An HTTP header value must be visible ASCII without line breaks.');
        }

        return new self($name, $value);
    }

    /**
     * Validates a header name alone (the request-id header, whose value is generated for each call).
     *
     * @throws InvalidValue
     */
    public static function validName(string $name): string
    {
        if (1 !== preg_match(self::NAME_PATTERN, $name)) {
            throw InvalidValue::because(self::NAME_INVALID, 'An HTTP header name must be an RFC 9110 token.');
        }

        if (\in_array(strtolower($name), self::RESERVED_NAMES, true)) {
            throw InvalidValue::because(
                self::NAME_RESERVED,
                'This HTTP header is set by the gateway and cannot be configured.',
            );
        }

        return $name;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function value(): string
    {
        return $this->value;
    }
}
