<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message\NonBlockingMessage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;

/**
 * Result of a pre-declaration, a dry run that records nothing (`OutputPreDeclaration`, api-reference §2.7). Every field
 * is optional in the contract; a 200 response may carry non-blocking messages (§4 decision 5).
 */
final readonly class PreDeclarationResult
{
    /**
     * @param list<ChildCheck>         $childChecks
     * @param list<NonBlockingMessage> $messages
     */
    private function __construct(
        private ?DeclarationAmounts $amounts,
        private bool $pajemploiPlus,
        private ?CalendarDate $transferDate,
        private array $childChecks,
        private array $messages,
    ) {}

    /**
     * @param DeclarationAmounts|null  $amounts       `declaration`
     * @param bool                     $pajemploiPlus `indcDeclPajemploiPlus` (default `false`)
     * @param CalendarDate|null        $transferDate  `dateVirement`
     * @param list<ChildCheck>         $childChecks   `verificationEnfantPe`
     * @param list<NonBlockingMessage> $messages      `erreursNonBloquantes`
     */
    public static function of(
        ?DeclarationAmounts $amounts,
        bool $pajemploiPlus,
        ?CalendarDate $transferDate,
        array $childChecks,
        array $messages,
    ): self {
        return new self($amounts, $pajemploiPlus, $transferDate, $childChecks, $messages);
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
