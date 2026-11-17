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

use DateInterval;
use DateTimeZone;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\AssociateEmployeeHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\CancelMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\DeclareSalaryHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ForgetEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ImportEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ImportMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ReconcileStaleDeclarationsHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RecordMandateCancellationHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RegisterMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RequeueAfterInvalidCredentialsHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ResolveDeclarationOutcomeHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\SyncEmployerStatusesHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\SyncEmployerStatusHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\TrackEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\EventPublisher;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\FailureReporter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\TransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\EstimateContributionsHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\FindDeclarationsHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetDeclarationHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetEmployerAccountHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetWorkflowOverviewHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\PreDeclareSalaryHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\VerifyChildrenHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\VerifyEmployeeHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\VerifyEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\DeclarationLocator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\DeclarationSubmitter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\EmployerLocator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\EmployerStatusRefresher;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\MandateGuard;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\MandateRecorder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\UnitOfWork;

/**
 * Application layer: workflow policy, services and use case handlers (the only public services of the bundle, with the
 * simulated gateway). The outbox handlers are registered by {@see OutboxRegistrar}.
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final readonly class ApplicationRegistrar
{
    /** Use case handlers exposed to the host application (the statistics and outbox ones are conditional). */
    public const HANDLERS = [
        TrackEmployerHandler::class,
        ImportEmployerHandler::class,
        RegisterMandateHandler::class,
        ImportMandateHandler::class,
        CancelMandateHandler::class,
        RecordMandateCancellationHandler::class,
        ForgetEmployerHandler::class,
        SyncEmployerStatusHandler::class,
        SyncEmployerStatusesHandler::class,
        AssociateEmployeeHandler::class,
        DeclareSalaryHandler::class,
        ResolveDeclarationOutcomeHandler::class,
        ReconcileStaleDeclarationsHandler::class,
        RequeueAfterInvalidCredentialsHandler::class,
        VerifyEmployerHandler::class,
        VerifyEmployeeHandler::class,
        VerifyChildrenHandler::class,
        PreDeclareSalaryHandler::class,
        EstimateContributionsHandler::class,
        GetEmployerAccountHandler::class,
        GetDeclarationHandler::class,
        FindDeclarationsHandler::class,
    ];

    /** Services shared by the handlers (private, autowired). */
    public const SERVICES = [
        EmployerLocator::class,
        EmployerStatusRefresher::class,
        MandateRecorder::class,
        MandateGuard::class,
        DeclarationLocator::class,
        DeclarationSubmitter::class,
    ];

    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        $workflow = $config['workflow'];
        // Named arguments: several parameters of the policy are booleans or integers of the same type.
        $this->services->set(WorkflowPolicy::class)
            ->arg('$businessTimeZone', new Definition(DateTimeZone::class, [$workflow['business_timezone']]))
            ->arg('$staleSubmissionAfter', new Definition(DateInterval::class, [$workflow['stale_submission_after']]))
            ->arg('$maxSubmissionAttempts', $workflow['max_submission_attempts'])
            ->arg('$requireLocalMandate', $workflow['require_local_mandate'])
            ->arg('$preDeclareBeforeDeclaring', $workflow['predeclare_before_declaring'])
            ->arg('$defaultPageSize', $workflow['default_page_size'])
            ->arg('$maxPageSize', $workflow['max_page_size'])
            ->arg('$allowErasureWithUnknownOutcomes', $workflow['erasure']['allow_unknown_outcomes'])
            ->arg('$allowErasureWithActiveMandate', $workflow['erasure']['allow_active_mandate'])
            ->arg('$outboxRetention', new Definition(DateInterval::class, [$config['outbox']['retention']]));
        // Explicit arguments: a missing alias must fail the build, not fall back on the defaults of the constructor.
        $this->services->set(UnitOfWork::class)->args([
            new Reference(TransactionManager::class),
            new Reference(EventPublisher::class),
            new Reference(FailureReporter::class),
            new Reference(Outbox::class),
        ]);
        foreach (self::SERVICES as $service) {
            $this->services->set($service)->autowire();
        }
        foreach (self::HANDLERS as $handler) {
            $this->services->set($handler)->autowire()->public();
        }
        if (PersistenceRegistrar::hasStatistics($config['persistence']['driver'], $config['persistence']['workflow_statistics'])) {
            $this->services->set(GetWorkflowOverviewHandler::class)->autowire()->public();
        }
    }
}
