<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support;

use Closure;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Override;

/**
 * DBAL middleware that runs an action once, right before the first DELETE statement is prepared: the window between the
 * SELECT and the DELETE of a two-step erasure, in which a concurrent writer can change a selected row.
 */
final class BeforeNextDelete implements Middleware
{
    private bool $fired = false;

    /**
     * @param Closure(): void $action
     */
    public function __construct(private readonly Closure $action) {}

    #[Override]
    public function wrap(Driver $driver): Driver
    {
        return new BeforeNextDeleteDriver($driver, $this);
    }

    public function fire(): void
    {
        if ($this->fired) {
            return;
        }
        $this->fired = true;
        ($this->action)();
    }
}
