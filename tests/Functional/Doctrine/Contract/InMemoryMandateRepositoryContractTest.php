<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryMandateRepository;

/**
 * Proves the contract itself: the in-memory repository satisfies the same scenarios as the Doctrine one.
 */
final class InMemoryMandateRepositoryContractTest extends TestCase
{
    use MandateRepositoryContract;

    protected function createRepository(): MandateRepository
    {
        return new InMemoryMandateRepository();
    }

    protected function anotherWriter(): MandateRepository
    {
        return $this->repository;
    }
}
