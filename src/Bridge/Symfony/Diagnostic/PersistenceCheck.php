<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic;

use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection\BundleConfiguration;

/**
 * Tells which persistence the records live in: the "in_memory" driver loses them when the process ends. The tables of the
 * "doctrine" driver are checked by {@see DatabaseSchemaCheck} (ADR 0014).
 */
final readonly class PersistenceCheck implements InstallationCheck
{
    private const NAME = 'persistence';

    /**
     * @param string $driver the "persistence.driver" option ("in_memory", "doctrine" or "custom")
     */
    public function __construct(private string $driver) {}

    #[Override]
    public function run(CheckContext $context): array
    {
        if ($this->driver === BundleConfiguration::DRIVER_IN_MEMORY) {
            return [new CheckResult(self::NAME, '', CheckStatus::Warning, 'In-memory persistence: every record is lost when the process ends (tests and prototypes only).')];
        }

        if ($this->driver === BundleConfiguration::DRIVER_DOCTRINE) {
            return [new CheckResult(self::NAME, '', CheckStatus::Ok, 'Doctrine repositories (see the "database schema" check).')];
        }

        return [new CheckResult(self::NAME, '', CheckStatus::Ok, 'Repositories of the application ("custom" driver).')];
    }
}
