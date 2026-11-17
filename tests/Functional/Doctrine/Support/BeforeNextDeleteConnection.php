<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Statement;
use Override;

/**
 * @internal connection decorated by {@see BeforeNextDelete}
 */
final class BeforeNextDeleteConnection extends AbstractConnectionMiddleware
{
    public function __construct(Connection $connection, private readonly BeforeNextDelete $middleware)
    {
        parent::__construct($connection);
    }

    #[Override]
    public function prepare(string $sql): Statement
    {
        if (str_starts_with($sql, 'DELETE FROM')) {
            $this->middleware->fire();
        }

        return parent::prepare($sql);
    }
}
