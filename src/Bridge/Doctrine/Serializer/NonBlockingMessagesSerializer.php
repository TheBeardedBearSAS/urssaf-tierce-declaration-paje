<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Serializer;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message\NonBlockingMessage;

/**
 * Explicit JSON shape of the non-blocking messages of an accepted declaration (a part of the result document): the
 * code, and the message and the description URSSAF sent (optional, personal data: ADR 0007).
 *
 * @phpstan-type MessageData array{code: string, message: string|null, description: string|null}
 */
final class NonBlockingMessagesSerializer
{
    /**
     * @param list<NonBlockingMessage> $messages
     *
     * @return list<MessageData>
     */
    public static function toArray(array $messages): array
    {
        return array_map(
            static fn(NonBlockingMessage $message): array => [
                'code' => $message->code(),
                'message' => $message->message(),
                'description' => $message->description(),
            ],
            $messages,
        );
    }

    /**
     * @param list<MessageData> $data
     *
     * @return list<NonBlockingMessage>
     */
    public static function fromArray(array $data): array
    {
        return array_map(
            static fn(array $message): NonBlockingMessage => NonBlockingMessage::of($message['code'], $message['message'], $message['description']),
            $data,
        );
    }
}
