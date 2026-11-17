<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\TransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineWorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryWorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Transaction\ImmediateTransactionManager;

/**
 * Repositories, transaction manager and statistics of the selected persistence driver ("in_memory", "doctrine" or
 * "custom"), ADR 0013 and ADR 0014.
 */
final readonly class PersistenceRegistrar
{
    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param array{driver: string, employer_account_repository: ?string, mandate_repository: ?string, declaration_record_repository: ?string, transaction_manager: ?string, workflow_statistics: ?string, entity_manager: string} $persistence
     */
    public function register(array $persistence): void
    {
        if ($persistence['driver'] === BundleConfiguration::DRIVER_DOCTRINE) {
            $this->registerDoctrine(new Reference(ServiceIds::entityManager($persistence['entity_manager'])));

            return;
        }
        if ($persistence['driver'] === BundleConfiguration::DRIVER_CUSTOM) {
            $this->registerCustom($persistence);

            return;
        }
        $this->services->set(EmployerAccountRepository::class, InMemoryEmployerAccountRepository::class);
        $this->services->set(MandateRepository::class, InMemoryMandateRepository::class);
        $this->services->set(DeclarationRecordRepository::class, InMemoryDeclarationRecordRepository::class);
        $this->services->set(TransactionManager::class, ImmediateTransactionManager::class);
        $this->services->set(WorkflowStatistics::class, InMemoryWorkflowStatistics::class)->args([
            new Reference(EmployerAccountRepository::class),
            new Reference(MandateRepository::class),
            new Reference(DeclarationRecordRepository::class),
        ]);
    }

    /**
     * Whether a WorkflowStatistics is available: always, except with the "custom" driver without "workflow_statistics".
     */
    public static function hasStatistics(string $driver, ?string $statisticsService): bool
    {
        return $driver !== BundleConfiguration::DRIVER_CUSTOM || $statisticsService !== null;
    }

    /**
     * @param array{driver: string, employer_account_repository: ?string, mandate_repository: ?string, declaration_record_repository: ?string, transaction_manager: ?string, workflow_statistics: ?string, entity_manager: string} $persistence
     */
    private function registerCustom(array $persistence): void
    {
        $this->services->alias(EmployerAccountRepository::class, (string) $persistence['employer_account_repository']);
        $this->services->alias(MandateRepository::class, (string) $persistence['mandate_repository']);
        $this->services->alias(DeclarationRecordRepository::class, (string) $persistence['declaration_record_repository']);
        $this->registerTransactionManager($persistence['transaction_manager']);
        if ($persistence['workflow_statistics'] !== null) {
            $this->services->alias(WorkflowStatistics::class, $persistence['workflow_statistics']);
        }
    }

    private function registerDoctrine(Reference $entityManager): void
    {
        $this->services->set(EmployerAccountRepository::class, DoctrineEmployerAccountRepository::class)->args([$entityManager]);
        $this->services->set(MandateRepository::class, DoctrineMandateRepository::class)->args([$entityManager]);
        $this->services->set(DeclarationRecordRepository::class, DoctrineDeclarationRecordRepository::class)->args([$entityManager]);
        $this->services->set(TransactionManager::class, DoctrineTransactionManager::class)->args([$entityManager]);
        $this->services->set(WorkflowStatistics::class, DoctrineWorkflowStatistics::class)->args([$entityManager]);
    }

    private function registerTransactionManager(?string $service): void
    {
        if ($service !== null) {
            $this->services->alias(TransactionManager::class, $service);

            return;
        }
        $this->services->set(TransactionManager::class, ImmediateTransactionManager::class);
    }
}
