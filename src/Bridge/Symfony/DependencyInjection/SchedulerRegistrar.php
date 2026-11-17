<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Scheduler\UrssafScheduleProvider;

/**
 * The schedule of the safety crons (ADR 0013, decision 10), registered under the name of "scheduler.name".
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final readonly class SchedulerRegistrar
{
    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        $frequencies = $config['scheduler']['frequencies'];
        // Without the outbox, its commands do not exist.
        $this->services->set(UrssafScheduleProvider::class)
            ->args([$config['outbox']['enabled'] ? $frequencies : [...$frequencies, 'relay_outbox' => null, 'purge_outbox' => null]])
            ->tag('scheduler.schedule_provider', ['name' => $config['scheduler']['name']]);
    }
}
