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

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationAmounts;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;

/**
 * Explicit JSON shape of a {@see DeclarationResult}: the document reference, the amounts, the Pajemploi+ flag, the
 * transfer date and the non-blocking messages (personal data of the employee, ADR 0007).
 *
 * The child checks are never written (ADR 0007: no child name nor birth date is persisted): the result is read back
 * without them, as the aggregate keeps it ({@see \TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord::markDeclared()}).
 *
 * @phpstan-import-type AmountsData from DeclarationAmountsSerializer
 * @phpstan-import-type MessageData from NonBlockingMessagesSerializer
 *
 * @phpstan-type ResultData array{documentReference: string|null, amounts: AmountsData|null, pajemploiPlus: bool, transferDate: string|null, messages: list<MessageData>}
 */
final class DeclarationResultSerializer
{
    /**
     * @return ResultData
     */
    public static function toArray(DeclarationResult $result): array
    {
        $amounts = $result->amounts();

        return [
            'documentReference' => $result->documentReference(),
            'amounts' => $amounts instanceof DeclarationAmounts ? DeclarationAmountsSerializer::toArray($amounts) : null,
            'pajemploiPlus' => $result->isPajemploiPlus(),
            'transferDate' => $result->transferDate()?->toString(),
            'messages' => NonBlockingMessagesSerializer::toArray($result->messages()),
        ];
    }

    /**
     * @param array<array-key, mixed> $data the document, of shape ResultData
     *
     * @throws InvalidValue
     */
    public static function fromArray(array $data): DeclarationResult
    {
        /** @var ResultData $data */
        $amounts = $data['amounts'];
        $transferDate = $data['transferDate'];

        return DeclarationResult::of(
            $data['documentReference'],
            $amounts === null ? null : DeclarationAmountsSerializer::fromArray($amounts),
            $data['pajemploiPlus'],
            $transferDate === null ? null : CalendarDate::fromString($transferDate),
            [],
            NonBlockingMessagesSerializer::fromArray($data['messages']),
        );
    }
}
