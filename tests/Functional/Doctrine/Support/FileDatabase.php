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

use Doctrine\DBAL\Driver\Middleware;
use Doctrine\ORM\EntityManagerInterface;

/**
 * A SQLite file shared by several entity managers (several units of work and connections on the same data), removed
 * with {@see self::delete()}.
 */
final class FileDatabase
{
    private readonly string $path;

    private bool $created = false;

    public function __construct()
    {
        $directory = TestDatabase::directory();
        if (!is_dir($directory)) {
            mkdir($directory, 0o777, true);
        }
        $this->path = $directory . '/' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    /**
     * A new entity manager on the file; the first one creates the schema.
     */
    public function entityManager(): EntityManagerInterface
    {
        if ($this->created) {
            return TestDatabase::openFile($this->path);
        }
        $this->created = true;

        return TestDatabase::createFile($this->path);
    }

    /**
     * A new entity manager on the file with DBAL middlewares; the schema is created when it is the first one.
     *
     * @param list<Middleware> $middlewares
     */
    public function entityManagerWith(array $middlewares): EntityManagerInterface
    {
        if ($this->created) {
            return TestDatabase::openFile($this->path, $middlewares);
        }
        $this->created = true;

        return TestDatabase::createFile($this->path, $middlewares);
    }

    public function delete(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }
}
