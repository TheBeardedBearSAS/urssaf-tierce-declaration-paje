<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Override;

/**
 * @internal driver decorated by {@see BeforeNextDelete}
 */
final class BeforeNextDeleteDriver extends AbstractDriverMiddleware
{
    public function __construct(Driver $driver, private readonly BeforeNextDelete $middleware)
    {
        parent::__construct($driver);
    }

    #[Override]
    public function connect(array $params): Connection
    {
        return new BeforeNextDeleteConnection(parent::connect($params), $this->middleware);
    }
}
