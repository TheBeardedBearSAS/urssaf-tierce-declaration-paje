<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command;

/**
 * The employee as selected on the command line, before any prompt: exactly one source (number, NIR to ask, or the decoded
 * identity file) already validated, so that an invalid usage is refused before the operator types anything.
 */
final readonly class EmployeeSelection
{
    /**
     * @param array<array-key, mixed>|null $identity the decoded --identity-payload file
     */
    public function __construct(
        public ?string $number,
        public bool $askNir,
        public bool $askBirthDate,
        public ?array $identity,
    ) {}
}
