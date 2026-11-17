<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\OnlineAccountState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * `OutputVerificationSp` (021 response, nested in the 023 response, api-reference §2.2, §5): every field is optional.
 *
 * - `numeroPajeSalarie`: absent or null gives null, the raw string otherwise (the official example returns the
 *   placeholder "string", so it is not validated);
 * - `indcAma`, `indcGed`: absent or null is `false`;
 * - `etatCompteEnLigne`: absent or null is `OnlineAccountState::of('')`, any string is kept raw.
 *
 * A field of the wrong type is refused, naming the field.
 */
final class EmployeeAccountMapper
{
    /**
     * @throws UnexpectedUrssafResponse
     */
    public static function decode(Payload $payload): EmployeeAccount
    {
        return EmployeeAccount::of(
            $payload->optionalString('numeroPajeSalarie'),
            $payload->optionalBool('indcAma') ?? false,
            $payload->optionalBool('indcGed') ?? false,
            OnlineAccountState::of($payload->optionalString('etatCompteEnLigne') ?? ''),
        );
    }
}
