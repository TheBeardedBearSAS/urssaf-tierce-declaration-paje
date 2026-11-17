<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeAssociation;

/**
 * Read model of the answer of method 023: whether the employee was created and associated, the resulting account when
 * URSSAF returned one, and the non-blocking messages (an already associated employee answers `ER_API_023_NB_0000`).
 */
final readonly class EmployeeAssociationView
{
    /**
     * @param list<NonBlockingMessageView> $messages
     */
    private function __construct(
        public bool $created,
        public bool $associated,
        public bool $alreadyAssociated,
        public ?EmployeeAccountView $account,
        public array $messages,
    ) {}

    public static function fromAssociation(EmployeeAssociation $association): self
    {
        $account = $association->account();

        return new self(
            $association->created(),
            $association->associated(),
            $association->alreadyAssociated(),
            $account instanceof EmployeeAccount ? EmployeeAccountView::fromAccount($account) : null,
            NonBlockingMessageView::fromMessages($association->messages()),
        );
    }
}
