<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Command;

use ArrayObject;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\CheckContext;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\CheckResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\CheckStatus;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\InstallationCheck;

/**
 * A check of the host that records the context it was run with.
 */
final readonly class RecordingCheck implements InstallationCheck
{
    /**
     * @param ArrayObject<int, string> $seen
     */
    public function __construct(private string $name, private ArrayObject $seen) {}

    public function run(CheckContext $context): array
    {
        $this->seen->append(\sprintf('%s:%d:%s', $this->name, \count($context->tenants), $context->remoteCallsAllowed ? 'remote' : 'local'));

        return [
            new CheckResult($this->name, '', CheckStatus::Ok, 'Fine.'),
            new CheckResult($this->name . '+', '', CheckStatus::Warning, 'Careful.'),
        ];
    }
}
