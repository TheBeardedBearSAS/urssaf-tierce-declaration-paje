<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Serializer;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\OnlineAccountState;

/**
 * Explicit JSON shape of the last method 020 answer of an employer: the authorisation flag and the raw online account
 * state, kept as URSSAF sent it (an undocumented value survives the round trip).
 *
 * @phpstan-type VerificationData array{authorised: bool, state: string}
 */
final class EmployerVerificationSerializer
{
    /**
     * @return VerificationData
     */
    public static function toArray(EmployerVerification $verification): array
    {
        return ['authorised' => $verification->authorised(), 'state' => $verification->state()->raw()];
    }

    /**
     * @param array<array-key, mixed> $data the document, of shape VerificationData
     */
    public static function fromArray(array $data): EmployerVerification
    {
        /** @var VerificationData $data */
        return EmployerVerification::of($data['authorised'], OnlineAccountState::of($data['state']));
    }
}
