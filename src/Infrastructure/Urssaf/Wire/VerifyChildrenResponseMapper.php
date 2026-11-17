<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * 022 response `OutputVerificationEnfantPe` (api-reference §2.3, §5). An absent `verificationEnfantPe` is the empty
 * list; an item without `reponseStatutEnfant` is skipped, the others keep their order ({@see ChildCheckMapper}). The
 * response has no `erreursNonBloquantes`.
 */
final class VerifyChildrenResponseMapper
{
    /**
     * @return list<ChildCheck>
     *
     * @throws UnexpectedUrssafResponse when the body is not a JSON object, the list is not a JSON array of objects or an item is malformed
     */
    public static function map(Payload $payload): array
    {
        $payload->requireJsonObject();

        $checks = [];

        foreach ($payload->items('verificationEnfantPe') as $item) {
            $check = ChildCheckMapper::decode($item);

            if ($check instanceof ChildCheck) {
                $checks[] = $check;
            }
        }

        return $checks;
    }
}
