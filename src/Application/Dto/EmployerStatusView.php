<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;

/**
 * Read model of the last answer of method 020 (api-reference §2.1): the employer is authorised and the raw state of its
 * online account (unknown values are kept). Never exposes personal data. Instants are ISO 8601 UTC strings.
 */
final readonly class EmployerStatusView
{
    private function __construct(
        public bool $authorised,
        public string $onlineAccountState,
        public ?string $checkedAt,
    ) {}

    public static function fromVerification(EmployerVerification $verification, ?Instant $checkedAt): self
    {
        return new self($verification->authorised(), $verification->state()->raw(), $checkedAt?->toIso8601());
    }

    /**
     * Null when the account was never synchronised.
     */
    public static function fromAccount(EmployerAccount $account): ?self
    {
        $verification = $account->lastVerification();
        if (!$verification instanceof EmployerVerification) {
            return null;
        }

        return self::fromVerification($verification, $account->lastCheckedAt());
    }
}
