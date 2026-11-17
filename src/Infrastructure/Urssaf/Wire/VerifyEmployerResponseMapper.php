<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\OnlineAccountState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * 020 response `OutputVerificationPe` (api-reference §2.1, §5): every field is optional. An absent (or null)
 * `employeurAutoriseOk` is `false`; an absent (or null) `etatCompteEnLigne` is `OnlineAccountState::of('')`; any
 * string is kept raw. A field of the wrong type is refused, naming the field.
 */
final class VerifyEmployerResponseMapper
{
    /**
     * @throws UnexpectedUrssafResponse when the body is not a JSON object (`{}` is accepted) or a field is malformed
     */
    public static function map(Payload $payload): EmployerVerification
    {
        $payload->requireJsonObject();

        return EmployerVerification::of(
            $payload->optionalBool('employeurAutoriseOk') ?? false,
            OnlineAccountState::of($payload->optionalString('etatCompteEnLigne') ?? ''),
        );
    }
}
