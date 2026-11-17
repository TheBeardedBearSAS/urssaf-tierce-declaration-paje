<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationAmounts;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationResult;

/**
 * Read model of what URSSAF answered for a declaration (methods 050 / 051) and the record keeps: the
 * `referenceDocumentaire`, the computed amounts, the Pajemploi+ flag, the transfer date (`yyyy-MM-dd`) and the
 * non-blocking messages. The child checks are never kept (ADR 0006). A result resolved by an operator holds the
 * reference only (`pajemploiPlus` is then false by convention, ADR 0006).
 */
final readonly class DeclarationResultView
{
    /**
     * @param list<NonBlockingMessageView> $messages
     */
    private function __construct(
        public ?string $documentReference,
        public ?DeclarationAmountsView $amounts,
        public bool $pajemploiPlus,
        public ?string $transferDate,
        public array $messages,
    ) {}

    public static function fromResult(DeclarationResult $result): self
    {
        $amounts = $result->amounts();

        return new self(
            $result->documentReference(),
            $amounts instanceof DeclarationAmounts ? DeclarationAmountsView::fromAmounts($amounts) : null,
            $result->isPajemploiPlus(),
            $result->transferDate()?->toString(),
            NonBlockingMessageView::fromMessages($result->messages()),
        );
    }
}
