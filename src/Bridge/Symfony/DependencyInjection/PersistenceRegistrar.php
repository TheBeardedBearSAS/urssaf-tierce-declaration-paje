<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\TransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryWorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Transaction\ImmediateTransactionManager;

/**
 * Repositories, transaction manager and statistics of the selected persistence driver ("in_memory" or "custom": the
 * "doctrine" driver is refused by the configuration until the Doctrine bridge ships).
 */
final readonly class PersistenceRegistrar
{
    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param array{driver: string, employer_account_repository: ?string, mandate_repository: ?string, declaration_record_repository: ?string, transaction_manager: ?string, workflow_statistics: ?string} $persistence
     */
    public function register(array $persistence): void
    {
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
     * @param array{driver: string, employer_account_repository: ?string, mandate_repository: ?string, declaration_record_repository: ?string, transaction_manager: ?string, workflow_statistics: ?string} $persistence
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

    private function registerTransactionManager(?string $service): void
    {
        if ($service !== null) {
            $this->services->alias(TransactionManager::class, $service);

            return;
        }
        $this->services->set(TransactionManager::class, ImmediateTransactionManager::class);
    }
}
