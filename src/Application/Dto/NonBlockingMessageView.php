<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message\NonBlockingMessage;

/**
 * Read model of a non-blocking message (`erreursNonBloquantes`): the raw code, the message and the description, which
 * may echo host values (masked in debug output, ADR 0007).
 */
final readonly class NonBlockingMessageView
{
    private function __construct(
        public string $code,
        public ?string $message,
        #[SensitiveParameter]
        public ?string $description,
    ) {}

    private static function fromMessage(NonBlockingMessage $message): self
    {
        return new self($message->code(), $message->message(), $message->description());
    }

    /**
     * @param list<NonBlockingMessage> $messages
     *
     * @return list<self>
     */
    public static function fromMessages(array $messages): array
    {
        $views = [];
        foreach ($messages as $message) {
            $views[] = self::fromMessage($message);
        }

        return $views;
    }

    /**
     * @return array<string, string|null>
     */
    public function __debugInfo(): array
    {
        return ['code' => $this->code, 'message' => $this->message, 'description' => $this->description === null ? null : '***'];
    }
}
