<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message\NonBlockingMessage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\ApiErrorCode;

/**
 * Reads `erreursNonBloquantes` of a 200 result (api-reference §1.3, ADR 0008). They never fail a call: an absent list
 * is empty, codes are normalised like those of the error payloads (inner whitespace removed, ADR 0005), an entry without a code keeps an empty code and fields of another type read as absent. A
 * malformed list (not a list, or entries that are not objects) is read leniently like the error payloads: it is empty
 * or its odd entries are skipped, so an informational warning never turns a recorded declaration into an unknown outcome.
 */
final class NonBlockingMessagesMapper
{
    private const FIELD = 'erreursNonBloquantes';

    /**
     * @return list<NonBlockingMessage>
     */
    public static function map(Payload $result): array
    {
        return array_map(
            static fn(Payload $entry): NonBlockingMessage => NonBlockingMessage::of(
                ApiErrorCode::normalise($entry->string('code') ?? ''),
                $entry->string('message'),
                $entry->string('description'),
            ),
            $result->lenientItems(self::FIELD),
        );
    }
}
