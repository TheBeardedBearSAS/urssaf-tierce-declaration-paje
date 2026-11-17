<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationAmounts;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\PreDeclarationResult;

/**
 * Read model of a pre-declaration (methods 045 / 046): the amounts URSSAF would compute, nothing was recorded. The child
 * checks give the entitlement status only, never the child identity (ADR 0007).
 */
final readonly class PreDeclarationView
{
    /**
     * @param list<ChildCheckView>         $childChecks
     * @param list<NonBlockingMessageView> $messages
     */
    private function __construct(
        public ?DeclarationAmountsView $amounts,
        public bool $pajemploiPlus,
        public ?string $transferDate,
        public array $childChecks,
        public array $messages,
    ) {}

    public static function fromResult(PreDeclarationResult $result): self
    {
        $amounts = $result->amounts();

        return new self(
            $amounts instanceof DeclarationAmounts ? DeclarationAmountsView::fromAmounts($amounts) : null,
            $result->isPajemploiPlus(),
            $result->transferDate()?->toString(),
            ChildCheckView::fromChecks($result->childChecks()),
            NonBlockingMessageView::fromMessages($result->messages()),
        );
    }
}
