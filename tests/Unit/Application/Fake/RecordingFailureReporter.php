<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake;

use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\FailureReporter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\DomainEvent;
use Throwable;

final class RecordingFailureReporter implements FailureReporter
{
    /** @var list<array{Throwable, list<DomainEvent>}> */
    private array $reports = [];

    #[Override]
    public function eventPublicationFailed(Throwable $failure, array $events): void
    {
        $this->reports[] = [$failure, $events];
    }

    /**
     * @return list<array{Throwable, list<DomainEvent>}>
     */
    public function reports(): array
    {
        return $this->reports;
    }
}
