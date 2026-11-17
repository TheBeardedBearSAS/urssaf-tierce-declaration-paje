<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message\NonBlockingMessage;

/**
 * Result of operation 023 `OutputAssociationEmployeurSalarie` (api-reference §2.4): `salarieCreeOk`,
 * `salarieAssocieOk`, `salarieDejaAssocieOK`, the nested `outputVerificationSp` and the non-blocking messages. A 200
 * answer is not necessarily clean (api-reference §4 decision 5).
 */
final readonly class EmployeeAssociation
{
    /**
     * @param list<NonBlockingMessage> $messages
     */
    private function __construct(
        private bool $created,
        private bool $associated,
        private bool $alreadyAssociated,
        private ?EmployeeAccount $account,
        private array $messages,
    ) {}

    /**
     * @param list<NonBlockingMessage> $messages
     */
    public static function of(
        bool $created,
        bool $associated,
        bool $alreadyAssociated,
        ?EmployeeAccount $account,
        array $messages,
    ): self {
        return new self($created, $associated, $alreadyAssociated, $account, $messages);
    }

    public function created(): bool
    {
        return $this->created;
    }

    public function associated(): bool
    {
        return $this->associated;
    }

    public function alreadyAssociated(): bool
    {
        return $this->alreadyAssociated;
    }

    public function account(): ?EmployeeAccount
    {
        return $this->account;
    }

    /**
     * @return list<NonBlockingMessage>
     */
    public function messages(): array
    {
        return $this->messages;
    }
}
