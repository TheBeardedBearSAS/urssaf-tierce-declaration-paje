<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf;

/**
 * Why the outcome of a never-resent call is unknown (ADR 0003).
 */
enum OutcomeUnknownReason: string
{
    /** The request may have been sent but no HTTP answer came back (connection reset, timeout). */
    case Transport = 'transport';

    /** URSSAF answered a 5xx (other than the authentication status 512). */
    case ServerError = 'server_error';

    /** URSSAF answered 2xx but the answer could not be read: the call was processed. */
    case UnreadableResponse = 'unreadable_response';
}
