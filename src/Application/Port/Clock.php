<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
