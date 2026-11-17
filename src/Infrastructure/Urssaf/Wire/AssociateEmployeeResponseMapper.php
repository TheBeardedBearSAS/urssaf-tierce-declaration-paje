<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeAssociation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * 023 response `OutputAssociationEmployeurSalarie` (api-reference §2.4, §5): every field is optional. An absent (or
 * null) `salarieCreeOk`, `salarieAssocieOk` or `salarieDejaAssocieOK` (upper-case `OK`) is `false`; an absent
 * `outputVerificationSp` is null (else {@see EmployeeAccountMapper}); an absent `erreursNonBloquantes` is the empty list
 * ({@see NonBlockingMessagesMapper}). A field of the wrong type is refused, naming the field.
 */
final class AssociateEmployeeResponseMapper
{
    /**
     * @throws UnexpectedUrssafResponse when the body is not a JSON object (`{}` is accepted) or a field is malformed
     */
    public static function map(Payload $payload): EmployeeAssociation
    {
        $payload->requireJsonObject();

        $account = $payload->object('outputVerificationSp');

        return EmployeeAssociation::of(
            $payload->optionalBool('salarieCreeOk') ?? false,
            $payload->optionalBool('salarieAssocieOk') ?? false,
            $payload->optionalBool('salarieDejaAssocieOK') ?? false,
            $account instanceof Payload ? EmployeeAccountMapper::decode($account) : null,
            NonBlockingMessagesMapper::map($payload),
        );
    }
}
