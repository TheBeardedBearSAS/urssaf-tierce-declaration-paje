<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Repository;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract\MandateRepositoryContract;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support\FileDatabase;

/**
 * The shared contract on a SQLite file (own connection per entity manager, real file locking): the other writer is an
 * entity manager of its own on the same file.
 */
final class DoctrineFileMandateRepositoryContractTest extends TestCase
{
    use MandateRepositoryContract {
        setUp as private contractSetUp;
    }

    private FileDatabase $file;

    private ?MandateRepository $other = null;

    protected function setUp(): void
    {
        $this->file = new FileDatabase();
        $this->contractSetUp();
    }

    protected function tearDown(): void
    {
        $this->file->delete();
    }

    protected function createRepository(): MandateRepository
    {
        return new DoctrineMandateRepository($this->file->entityManager());
    }

    protected function anotherWriter(): MandateRepository
    {
        return $this->other ??= new DoctrineMandateRepository($this->file->entityManager());
    }
}
