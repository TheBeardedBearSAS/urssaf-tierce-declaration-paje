<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\LockFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Lock\OperationLocks;

/**
 * The operation locks: backed by the configured lock factory when enabled ("auto": when symfony/lock is installed and
 * the factory service exists), a pass-through otherwise.
 */
final readonly class LockRegistrar
{
    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param array{enabled: bool|string, factory: string, ttl: float} $lock
     */
    public function register(array $lock): void
    {
        $this->services->set(OperationLocks::class)->args([self::factoryArgument($lock, class_exists(LockFactory::class)), $lock['ttl']]);
    }

    /**
     * The lock factory argument: null when the locks are disabled or symfony/lock is missing, a mandatory reference
     * when enabled, an optional one with "auto".
     *
     * @param array{enabled: bool|string, factory: string, ttl: float} $lock
     */
    public static function factoryArgument(array $lock, bool $componentInstalled): ?Reference
    {
        return match (true) {
            $lock['enabled'] === false || !$componentInstalled => null,
            $lock['enabled'] === true => new Reference($lock['factory']),
            default => new Reference($lock['factory'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
        };
    }
}
