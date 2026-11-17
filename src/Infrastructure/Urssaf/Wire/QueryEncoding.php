<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use SensitiveParameter;

/**
 * Query string of the operations that carry parameters (ADR 0002): RFC 3986 percent-encoding (a space is `%20`,
 * never `+`).
 *
 * The parameters carry personal data (employer number, birth dates, NIR): a query string is never logged and never put
 * in an exception message (ADR 0007).
 */
final class QueryEncoding
{
    /**
     * Parameters whose value is null are dropped (PHP skips them). Empty string when there is no parameter left; the
     * result has no leading `?`.
     *
     * @param array<string, string|null> $parameters
     */
    public static function build(#[SensitiveParameter] array $parameters): string
    {
        return http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }
}
