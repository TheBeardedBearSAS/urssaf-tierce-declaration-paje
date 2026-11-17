<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Contract;

use JsonException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\OnlineAccountStateValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\ApiErrorCode;

/**
 * The only thing ever kept from a wire exchange: its shape. Never a request body (the token request carries the
 * secret), never an Authorization header, never a query string (it carries the birth dates and the NIR), never a
 * free-text response value (`message`, `description`, names, numbers, references). Enumerated values are copied only
 * from closed sets or from a strict shape, anything else is reduced to its length.
 *
 * One value object and one extractor per whitelisted field of the wire, hence the size and the parts of the
 * constructor (the public constructor is the factory, as in src).
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class Exchange
{
    public const EMPTY = 'empty';
    public const OBJECT = 'object';
    public const LIST = 'list';
    public const SCALAR = 'scalar';
    public const NOT_JSON = 'not_json';

    private const NUMBER_SEGMENT = '/^[0-9A-Za-z]{14}$/D';
    private const RETRY_AFTER_SECONDS = '/^\d{1,9}$/D';
    private const RETRY_AFTER_DATE = '/^[A-Z][a-z]{2}, \d{2} [A-Z][a-z]{2} \d{4} \d{2}:\d{2}:\d{2} GMT$/D';
    /** A catalogue code: upper case letters, digits and underscores (the ER_API_* family has digits). */
    private const CODE_SHAPE = '/^[A-Z][A-Z0-9_]{2,40}$/D';
    private const AMOUNT = '/"(mnt[A-Za-z0-9]*)"\s*:\s*-?\d+(?:\.(\d+))?/';
    private const RATE = '/"txPas"\s*:\s*-?\d+(?:\.(\d+))?/';
    private const TYPED_KEYS = [
        'employeurAutoriseOk',
        'indcAma',
        'indcGed',
        'numeroPajeSalarie',
        'salarieCreeOk',
        'salarieAssocieOk',
        'salarieDejaAssocieOK',
        'indcDeclPajemploiPlus',
        'referenceDocumentaire',
        'dateVirement',
        'expires_in',
    ];

    /**
     * @param list<string>          $headerNames
     * @param list<string>          $bodyKeys          top-level keys of the object (or of the first item of a list)
     * @param array<string, string> $enumValues
     * @param array<string, string> $jsonTypes
     * @param list<string>          $codes             `code` of every error of a 4xx / 5xx payload, shape checked
     * @param list<string>          $nonBlockingCodes  `code` of every `erreursNonBloquantes` item, shape checked
     * @param null|int              $amountMaxDecimals most decimals among the `mnt*` numbers, null without any
     * @param null|int              $rateMaxDecimals   decimals of `txPas`, null when absent
     *
     * @mago-expect lint:excessive-parameter-list
     */
    public function __construct(
        public string $method,
        public string $host,
        public string $pathTemplate,
        public int $status,
        public array $headerNames,
        public ?string $retryAfter,
        public string $bodyShape,
        public array $bodyKeys,
        public array $enumValues,
        public array $jsonTypes,
        public array $codes,
        public array $nonBlockingCodes,
        public ?int $amountMaxDecimals,
        public ?int $rateMaxDecimals,
    ) {}

    public static function from(RequestInterface $request, ResponseInterface $response): self
    {
        $uri = $request->getUri();
        // Only the path is kept, with the 14-character numbers templated: never the query string.
        $segments = array_map(
            static fn(string $segment): string => 1 === preg_match(self::NUMBER_SEGMENT, $segment) ? '{numero}' : $segment,
            explode('/', $uri->getPath()),
        );

        $headerNames = array_map(strtolower(...), array_map(strval(...), array_keys($response->getHeaders())));
        sort($headerNames);

        $body = $response->getBody();
        $text = (string) $body;
        if ($body->isSeekable()) {
            $body->rewind();
        }
        $shape = self::analyse($text);

        return new self(
            $request->getMethod(),
            $uri->getHost(),
            implode('/', $segments),
            $response->getStatusCode(),
            $headerNames,
            self::retryAfter($response->getHeaderLine('Retry-After')),
            $shape['shape'],
            $shape['keys'],
            $shape['enums'],
            $shape['types'],
            $shape['codes'],
            $shape['nonBlocking'],
            self::maxDecimals($text, self::AMOUNT, 2),
            self::maxDecimals($text, self::RATE, 1),
        );
    }

    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * Every code of the exchange, blocking or not.
     *
     * @return list<string>
     */
    public function allCodes(): array
    {
        return array_values(array_unique([...$this->codes, ...$this->nonBlockingCodes]));
    }

    private static function retryAfter(string $raw): ?string
    {
        if ('' === $raw) {
            return null;
        }

        return 1 === preg_match(self::RETRY_AFTER_SECONDS, $raw) || 1 === preg_match(self::RETRY_AFTER_DATE, $raw)
            ? $raw
            : 'unparseable';
    }

    /**
     * Number of decimals of the numbers read by the pattern (the text is only counted, never kept).
     */
    private static function maxDecimals(string $text, string $pattern, int $group): ?int
    {
        $matches = [];
        if (0 === preg_match_all($pattern, $text, $matches, \PREG_SET_ORDER)) {
            return null;
        }

        $max = 0;
        foreach ($matches as $match) {
            $max = max($max, \strlen($match[$group] ?? ''));
        }

        return $max;
    }

    /**
     * @return array{shape: string, keys: list<string>, enums: array<string, string>, types: array<string, string>, codes: list<string>, nonBlocking: list<string>}
     */
    private static function analyse(string $text): array
    {
        $none = ['keys' => [], 'enums' => [], 'types' => [], 'codes' => [], 'nonBlocking' => []];
        if ('' === trim($text)) {
            return ['shape' => self::EMPTY] + $none;
        }

        try {
            // @mago-expect analysis:mixed-assignment Decoded JSON is untyped: it is narrowed to an array right below.
            $decoded = json_decode($text, true, 64, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ['shape' => self::NOT_JSON] + $none;
        }

        if (!\is_array($decoded)) {
            return ['shape' => self::SCALAR] + $none;
        }

        $isList = array_is_list($decoded);
        // @mago-expect analysis:mixed-assignment The first element of a decoded list is untyped: it is narrowed right below.
        $object = $isList ? ($decoded[0] ?? []) : $decoded;
        if (!\is_array($object) || array_is_list($object) && [] !== $object) {
            $object = [];
        }
        $keys = array_map(strval(...), array_keys($object));
        sort($keys);

        return [
            'shape' => $isList ? self::LIST : self::OBJECT,
            'keys' => $keys,
            'enums' => self::enumValues($object),
            'types' => self::jsonTypes($object),
            'codes' => self::codesOf($isList ? $decoded : [$decoded]),
            'nonBlocking' => self::codesOf(\is_array($object['erreursNonBloquantes'] ?? null) ? $object['erreursNonBloquantes'] : []),
        ];
    }

    /**
     * @param array<array-key, mixed> $items
     *
     * @return list<string>
     */
    private static function codesOf(array $items): array
    {
        $codes = [];
        // @mago-expect analysis:mixed-assignment A decoded JSON item is untyped: it is narrowed to an array right below.
        foreach ($items as $item) {
            if (!\is_array($item) || !\is_string($item['code'] ?? null)) {
                continue;
            }

            $codes[] = self::code($item['code']);
        }

        return $codes;
    }

    private static function code(string $raw): string
    {
        $code = ApiErrorCode::normalise($raw);

        return 1 === preg_match(self::CODE_SHAPE, $code) ? $code : \sprintf('other(length=%d)', mb_strlen($code));
    }

    /**
     * @param array<array-key, mixed> $object
     *
     * @return array<string, string>
     */
    private static function enumValues(array $object): array
    {
        $enums = [];
        // @mago-expect analysis:mixed-assignment A decoded JSON value is untyped: it is narrowed to a string right below.
        $state = $object['etatCompteEnLigne'] ?? null;
        if (\is_string($state)) {
            $documented = array_map(static fn(OnlineAccountStateValue $value): string => $value->value, OnlineAccountStateValue::cases());
            $enums['etatCompteEnLigne'] = \in_array($state, $documented, true)
                ? $state
                : \sprintf('other(length=%d)', mb_strlen($state));
        }

        // @mago-expect analysis:mixed-assignment A decoded JSON value is untyped: it is narrowed to a string right below.
        $tokenType = $object['token_type'] ?? null;
        if (\is_string($tokenType)) {
            $enums['token_type'] = \in_array($tokenType, ['Bearer', 'bearer'], true) ? $tokenType : \sprintf('other(length=%d)', mb_strlen($tokenType));
        }

        if (\is_string($object['code'] ?? null)) {
            $enums['code'] = self::code($object['code']);
        }

        return $enums;
    }

    /**
     * @param array<array-key, mixed> $object
     *
     * @return array<string, string>
     */
    private static function jsonTypes(array $object): array
    {
        $types = [];
        foreach (self::TYPED_KEYS as $key) {
            if (!\array_key_exists($key, $object)) {
                continue;
            }

            $types[$key] = match (true) {
                \is_string($object[$key]) => 'string',
                \is_int($object[$key]) => 'integer',
                \is_float($object[$key]) => 'float',
                \is_bool($object[$key]) => 'bool',
                null === $object[$key] => 'null',
                \is_array($object[$key]) && array_is_list($object[$key]) => 'array',
                default => 'object',
            };
        }

        return $types;
    }
}
