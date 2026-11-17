<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message\NonBlockingMessage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;

/**
 * Result of a recorded declaration (`OutputDeclaration`, api-reference §2.9): the pre-declaration fields plus the
 * reference of the *volet social* (`referenceDocumentaire`, optional in the Swagger, so nullable).
 */
final readonly class DeclarationResult
{
    /**
     * @param list<ChildCheck>         $childChecks
     * @param list<NonBlockingMessage> $messages
     */
    private function __construct(
        private ?string $documentReference,
        private ?DeclarationAmounts $amounts,
        private bool $pajemploiPlus,
        private ?CalendarDate $transferDate,
        private array $childChecks,
        private array $messages,
    ) {}

    /**
     * @param string|null              $documentReference `referenceDocumentaire`
     * @param DeclarationAmounts|null  $amounts           `declaration`
     * @param bool                     $pajemploiPlus     `indcDeclPajemploiPlus` (default `false`)
     * @param CalendarDate|null        $transferDate      `dateVirement`
     * @param list<ChildCheck>         $childChecks       `verificationEnfantPe`
     * @param list<NonBlockingMessage> $messages          `erreursNonBloquantes`
     *
     * @mago-expect lint:excessive-parameter-list
     */
    public static function of(
        ?string $documentReference,
        ?DeclarationAmounts $amounts,
        bool $pajemploiPlus,
        ?CalendarDate $transferDate,
        array $childChecks,
        array $messages,
    ): self {
        return new self($documentReference, $amounts, $pajemploiPlus, $transferDate, $childChecks, $messages);
    }

    public function documentReference(): ?string
    {
        return $this->documentReference;
    }

    public function amounts(): ?DeclarationAmounts
    {
        return $this->amounts;
    }

    public function isPajemploiPlus(): bool
    {
        return $this->pajemploiPlus;
    }

    public function transferDate(): ?CalendarDate
    {
        return $this->transferDate;
    }

    /**
     * @return list<ChildCheck>
     */
    public function childChecks(): array
    {
        return $this->childChecks;
    }

    /**
     * @return list<NonBlockingMessage>
     */
    public function messages(): array
    {
        return $this->messages;
    }
}
