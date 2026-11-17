<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Contract;

use PHPUnit\Framework\Assert;
use ReflectionClass;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\ApiErrorCode;
use Throwable;

/**
 * Assertions and failure messages that never echo URSSAF texts (the API may repeat a submitted number or name): codes
 * only, and only when they have the shape of a catalogue code.
 */
final class SandboxAssert
{
    private const CODE_SHAPE = '/^[A-Z][A-Z0-9_]{2,40}$/D';

    /**
     * The codes of an error payload that look like catalogue codes, anything else reduced to its length.
     *
     * @return list<string>
     */
    public static function codes(ApiErrors $errors): array
    {
        return array_map(
            static function (string $raw): string {
                $code = ApiErrorCode::normalise($raw);

                return 1 === preg_match(self::CODE_SHAPE, $code) ? $code : \sprintf('other(length=%d)', mb_strlen($code));
            },
            $errors->codes(),
        );
    }

    /**
     * @return list<string> the codes of the errors that are not in the ApiErrorCode catalogue
     */
    public static function uncatalogued(ApiErrors $errors): array
    {
        return array_values(array_filter(self::codes($errors), static fn(string $code): bool => !ApiErrorCode::fromCode($code) instanceof ApiErrorCode));
    }

    /**
     * @param list<string> $codes
     */
    public static function assertCatalogued(array $codes): void
    {
        Assert::assertSame([], array_values(array_unique($codes)), \sprintf('Error codes not in the ApiErrorCode catalogue: %s.', implode(', ', array_unique($codes))));
    }

    /**
     * Class, status, kind and codes only: never getMessage() nor a description.
     */
    public static function describe(Throwable $throwable): string
    {
        $details = match (true) {
            $throwable instanceof UrssafRequestRejected => [
                'operation=' . $throwable->operation()->value,
                'status=' . $throwable->httpStatus(),
                'kind=' . $throwable->kind()->value,
                'codes=' . implode(',', self::codes($throwable->errors())),
            ],
            $throwable instanceof UrssafUnavailable, $throwable instanceof OutcomeUnknown => [
                'operation=' . $throwable->operation()->value,
                'status=' . ($throwable->httpStatus() ?? 'none'),
                'codes=' . implode(',', self::codes($throwable->errors())),
            ],
            $throwable instanceof UrssafAuthenticationFailed => ['status=' . $throwable->httpStatus(), self::authenticationHint()],
            default => [],
        };

        return implode(' ', [(new ReflectionClass($throwable))->getShortName(), ...$details]);
    }

    public static function authenticationHint(): string
    {
        return 'Token endpoint refused the sandbox credentials; see the token endpoint test and URSSAF_SANDBOX_TOKEN_URI.';
    }
}
