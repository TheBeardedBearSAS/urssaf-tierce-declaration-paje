<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\ContributionEstimate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * `OutputEstimation` of 040 / 041 (api-reference §2.5). Every field is optional in the Swagger and none is bounded
 * (ADR 0008): an absent amount is null, absent messages are an empty list (they never fail the call). A malformed
 * value is refused, naming the field and never its value.
 */
final class ContributionEstimateResponseMapper
{
    /**
     * @throws UnexpectedUrssafResponse when the body is not a JSON object (`{}` is accepted) or a field is malformed
     */
    public static function map(Payload $payload): ContributionEstimate
    {
        $payload->requireJsonObject();

        return ContributionEstimate::of(
            AmountEncoding::decodeOptional($payload->numeric('mntCotiPriseEnChargeOdpf'), 'mntCotiPriseEnChargeOdpf'),
            AmountEncoding::decodeOptional($payload->numeric('mntCotiEmplAcharge'), 'mntCotiEmplAcharge'),
            AmountEncoding::decodeOptional($payload->numeric('mntExoEmployeur'), 'mntExoEmployeur'),
            NonBlockingMessagesMapper::map($payload),
        );
    }
}
