<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Repository;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract\EmployerAccountRepositoryContract;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support\FileDatabase;

/**
 * The shared contract on a SQLite file (own connection per entity manager, real file locking): the other writer is an
 * entity manager of its own on the same file.
 */
final class DoctrineFileEmployerAccountRepositoryContractTest extends TestCase
{
    use EmployerAccountRepositoryContract {
        setUp as private contractSetUp;
    }

    private FileDatabase $file;

    private ?EmployerAccountRepository $other = null;

    protected function setUp(): void
    {
        $this->file = new FileDatabase();
        $this->contractSetUp();
    }

    protected function tearDown(): void
    {
        $this->file->delete();
    }

    protected function createRepository(): EmployerAccountRepository
    {
        return new DoctrineEmployerAccountRepository($this->file->entityManager());
    }

    protected function anotherWriter(): EmployerAccountRepository
    {
        return $this->other ??= new DoctrineEmployerAccountRepository($this->file->entityManager());
    }
}
