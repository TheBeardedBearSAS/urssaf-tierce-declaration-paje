<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\DependencyInjection;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Proxy\ProxyFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerAggregate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\TransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetWorkflowOverview;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetWorkflowOverviewHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineOutbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineWorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Outbox\InMemoryOutbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Transaction\ImmediateTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Scenario;

/**
 * The "doctrine" persistence driver: services, mapping, types, named entity manager and an end-to-end declaration
 * (ADR 0013 decision 3, ADR 0014).
 */
final class DoctrineWiringTest extends TestCase
{
    public function testTheDoctrineDriverWiresTheRepositoriesTheTransactionManagerAndTheStatistics(): void
    {
        $harness = self::doctrine('default');

        static::assertInstanceOf(DoctrineEmployerAccountRepository::class, $harness->privateService(EmployerAccountRepository::class, EmployerAccountRepository::class));
        static::assertInstanceOf(DoctrineMandateRepository::class, $harness->privateService(MandateRepository::class, MandateRepository::class));
        static::assertInstanceOf(DoctrineDeclarationRecordRepository::class, $harness->privateService(DeclarationRecordRepository::class, DeclarationRecordRepository::class));
        static::assertInstanceOf(DoctrineTransactionManager::class, $harness->privateService(TransactionManager::class, TransactionManager::class));
        static::assertInstanceOf(DoctrineWorkflowStatistics::class, $harness->privateService(WorkflowStatistics::class, WorkflowStatistics::class));
    }

    public function testTheOutboxIsTheDoctrineOneUnlessAServiceIsGiven(): void
    {
        $doctrine = Harness::boot(['persistence' => ['driver' => 'doctrine'], 'outbox' => ['enabled' => true, 'sink' => 'test.sink']], false, false, true);
        $custom = Harness::boot(['persistence' => ['driver' => 'doctrine'], 'outbox' => ['enabled' => true, 'sink' => 'test.sink', 'service' => 'test.outbox']], false, false, true);

        static::assertInstanceOf(DoctrineOutbox::class, $doctrine->privateService(Outbox::class, Outbox::class));
        static::assertInstanceOf(InMemoryOutbox::class, $custom->privateService(Outbox::class, Outbox::class));
    }

    public function testTheMappingAndTheTypesAreRegisteredInTheDefaultEntityManager(): void
    {
        $harness = self::doctrine('default');
        $entityManager = $harness->privateService('doctrine.orm.default_entity_manager', EntityManagerInterface::class);

        static::assertFalse($entityManager->getMetadataFactory()->isTransient(EmployerAccount::class));
        static::assertFalse($entityManager->getMetadataFactory()->isTransient(Mandate::class));
        static::assertFalse($entityManager->getMetadataFactory()->isTransient(DeclarationRecord::class));
        // The bundle leaves the proxy generation mode to the host (doctrine-bundle default outside debug: never); with
        // native lazy objects, no proxy is generated at all and the mode is not configured.
        static::assertSame(
            DoctrineIntegration::nativeLazyObjectsAvailable() ? ProxyFactory::AUTOGENERATE_ALWAYS : ProxyFactory::AUTOGENERATE_NEVER,
            $entityManager->getConfiguration()->getAutoGenerateProxyClasses(),
        );
        if (DoctrineIntegration::nativeLazyObjectsAvailable()) {
            static::assertTrue($entityManager->getConfiguration()->isNativeLazyObjectsEnabled());
        }
        foreach (array_keys(DoctrineIntegration::types()) as $type) {
            static::assertTrue(Type::hasType($type), $type);
        }
    }

    public function testAnotherEntityManagerCanBeSelected(): void
    {
        $harness = Harness::boot(
            ['persistence' => ['driver' => 'doctrine', 'entity_manager' => 'urssaf']],
            false,
            false,
            [
                'dbal' => ['connections' => ['default' => ['driver' => 'pdo_sqlite', 'memory' => true], 'urssaf' => ['driver' => 'pdo_sqlite', 'memory' => true]]],
                'orm' => [
                    'enable_lazy_ghost_objects' => true,
                    'entity_managers' => ['default' => ['connection' => 'default'], 'urssaf' => ['connection' => 'urssaf']],
                ],
            ],
        );

        $mapped = $harness->privateService('doctrine.orm.urssaf_entity_manager', EntityManagerInterface::class);
        $other = $harness->privateService('doctrine.orm.default_entity_manager', EntityManagerInterface::class);
        static::assertFalse($mapped->getMetadataFactory()->isTransient(EmployerAccount::class));
        static::assertTrue($other->getMetadataFactory()->isTransient(EmployerAccount::class));
        static::assertInstanceOf(DoctrineEmployerAccountRepository::class, $harness->privateService(EmployerAccountRepository::class, EmployerAccountRepository::class));
    }

    public function testNothingIsMappedWhenAnotherDriverIsSelected(): void
    {
        $harness = Harness::boot(['persistence' => ['driver' => 'in_memory']], false, false, true);
        $entityManager = $harness->privateService('doctrine.orm.default_entity_manager', EntityManagerInterface::class);

        static::assertTrue($entityManager->getMetadataFactory()->isTransient(EmployerAccount::class));
        static::assertInstanceOf(InMemoryEmployerAccountRepository::class, $harness->privateService(EmployerAccountRepository::class, EmployerAccountRepository::class));
        static::assertInstanceOf(InMemoryMandateRepository::class, $harness->privateService(MandateRepository::class, MandateRepository::class));
        static::assertInstanceOf(ImmediateTransactionManager::class, $harness->privateService(TransactionManager::class, TransactionManager::class));
    }

    public function testTheProxyCacheWarmerGeneratesTheProxiesOfTheMappedEntitiesWhichThenLoadLazily(): void
    {
        $native = DoctrineIntegration::nativeLazyObjectsAvailable();
        $harness = Harness::boot(
            ['persistence' => ['driver' => 'doctrine']],
            false,
            false,
            ['dbal' => ['driver' => 'pdo_sqlite', 'memory' => true], 'orm' => ['enable_lazy_ghost_objects' => true, 'auto_generate_proxy_classes' => false]],
        );
        $entityManager = $harness->privateService('doctrine.orm.default_entity_manager', EntityManagerInterface::class);
        if (!$native) {
            $this->assertProxiesAreWarmedUp($harness, $entityManager);
        }
        $harness->createDatabaseSchema();
        $account = Scenario::importedEmployer($harness);
        $entityManager->clear();

        $reference = $entityManager->getReference(EmployerAccount::class, EmployerAccountId::fromString($account->accountId));

        static::assertInstanceOf(EmployerAccount::class, $reference);
        static::assertTrue($entityManager->getUnitOfWork()->isUninitializedObject($reference));
        static::assertSame(Scenario::EMPLOYER, $reference->externalReference()->value());
        static::assertFalse($entityManager->getUnitOfWork()->isUninitializedObject($reference));
    }

    public function testADeclarationIsPersistedEndToEnd(): void
    {
        $harness = self::doctrine('default');
        $harness->createDatabaseSchema();
        $entityManager = $harness->privateService('doctrine.orm.default_entity_manager', EntityManagerInterface::class);

        $submission = Scenario::declaration($harness);
        $entityManager->clear();

        static::assertSame('declared', $submission->declaration->status->state);
        $record = $harness->privateService(DeclarationRecordRepository::class, DeclarationRecordRepository::class)
            ->findByExternalReference(TenantId::fromString(Scenario::TENANT), ExternalReference::fromString(Scenario::DECLARATION));
        static::assertNotNull($record);
        static::assertSame(DeclarationState::Declared, $record->state());
        $overview = $harness->get(GetWorkflowOverviewHandler::class)(GetWorkflowOverview::forTenant(TenantId::fromString(Scenario::TENANT)))[0];
        static::assertSame(1, $overview->declarations['declared']);
        static::assertSame(1, $overview->mandates['registered']);
    }

    private function assertProxiesAreWarmedUp(Harness $harness, EntityManagerInterface $entityManager): void
    {
        $proxyDirectory = $entityManager->getConfiguration()->getProxyDir();
        static::assertIsString($proxyDirectory);
        (new Filesystem())->remove($proxyDirectory);

        $harness->privateService('cache_warmer', CacheWarmerAggregate::class)->warmUp($harness->kernel->getCacheDir());

        $files = glob($proxyDirectory . '/*.php');
        static::assertIsArray($files);
        $proxies = array_map(basename(...), $files);
        sort($proxies);
        static::assertSame(
            [
                '__CG__TheBeardedBearUrssafTierceDeclarationPajeBridgeDoctrineEntityActiveMandate.php',
                '__CG__TheBeardedBearUrssafTierceDeclarationPajeBridgeDoctrineEntityOutboxMessage.php',
                '__CG__TheBeardedBearUrssafTierceDeclarationPajeDomainDeclarationDeclarationRecord.php',
                '__CG__TheBeardedBearUrssafTierceDeclarationPajeDomainEmployerEmployerAccount.php',
                '__CG__TheBeardedBearUrssafTierceDeclarationPajeDomainMandateMandate.php',
            ],
            $proxies,
        );
    }

    private static function doctrine(string $entityManager): Harness
    {
        return Harness::boot(['persistence' => ['driver' => 'doctrine', 'entity_manager' => $entityManager]], false, false, true);
    }
}
