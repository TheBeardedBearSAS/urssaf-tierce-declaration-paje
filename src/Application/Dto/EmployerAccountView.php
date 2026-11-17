<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;

/**
 * Read model of an employer account (primitives only, no birth date). Instants are ISO 8601 UTC strings. The employer
 * number is credential-like (ADR 0007): the view is for the host, never for a log.
 */
final readonly class EmployerAccountView
{
    private function __construct(
        public string $accountId,
        public string $tenantId,
        public string $externalReference,
        public string $employerNumber,
        public string $origin,
        public string $stage,
        public ?EmployerStatusView $lastStatus,
        public ?MandateView $mandate,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    public static function fromAccount(EmployerAccount $account, ?Mandate $mandate): self
    {
        return new self(
            $account->id()->value(),
            $account->tenantId()->value(),
            $account->externalReference()->value(),
            $account->employerNumber()->value(),
            $account->origin()->value,
            $account->stage()->value,
            EmployerStatusView::fromAccount($account),
            $mandate instanceof Mandate ? MandateView::fromMandate($mandate) : null,
            $account->createdAt()->toIso8601(),
            $account->updatedAt()->toIso8601(),
        );
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return [
            'accountId' => $this->accountId,
            'tenantId' => $this->tenantId,
            'externalReference' => $this->externalReference,
            'employerNumber' => '***',
            'origin' => $this->origin,
            'stage' => $this->stage,
        ];
    }
}
