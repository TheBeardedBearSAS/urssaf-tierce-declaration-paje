<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Standalone;

use LogicException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\AssociateEmployeeHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\CancelMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\DeclareSalaryHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ForgetEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ImportEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ImportMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\PurgeOutboxHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ReconcileStaleDeclarationsHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RecordMandateCancellationHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RegisterMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RelayOutboxHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RequeueAfterInvalidCredentialsHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ResolveDeclarationOutcomeHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\SyncEmployerStatusesHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\SyncEmployerStatusHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\TrackEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\PajeGateway;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\WorkflowStatistics;
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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;

/**
 * Every use case of the package wired together, for applications without the Symfony bundle; built by
 * {@see UrssafTierceDeclarationPajeBuilder}. Each method returns a new handler (handlers are stateless).
 *
 * One accessor per use case, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final readonly class UrssafTierceDeclarationPaje
{
    private const STATISTICS_MISSING = 'No WorkflowStatistics: give one with the repositories (withRepositories()).';
    private const SINK_MISSING = 'No IntegrationEventSink: give the outbox and its sink (withOutbox()).';

    public function __construct(
        private PajeGateway $gateway,
        private EmployerAccountRepository $accounts,
        private MandateRepository $mandates,
        private DeclarationRecordRepository $declarations,
        private UnitOfWork $unitOfWork,
        private Clock $clock,
        private IdGenerator $ids,
        private WorkflowPolicy $policy,
        private ?WorkflowStatistics $statistics,
        private Outbox $outbox,
        private ?IntegrationEventSink $sink,
    ) {}

    public function gateway(): PajeGateway
    {
        return $this->gateway;
    }

    public function associateEmployee(): AssociateEmployeeHandler
    {
        return new AssociateEmployeeHandler($this->gateway);
    }

    public function cancelMandate(): CancelMandateHandler
    {
        return new CancelMandateHandler($this->employerLocator(), $this->mandates, $this->gateway, $this->mandateRecorder());
    }

    public function declareSalary(): DeclareSalaryHandler
    {
        return new DeclareSalaryHandler($this->declarations, $this->accounts, $this->submitter(), $this->mandateGuard(), $this->ids, $this->clock, $this->unitOfWork, $this->policy);
    }

    public function forgetEmployer(): ForgetEmployerHandler
    {
        return new ForgetEmployerHandler($this->employerLocator(), $this->accounts, $this->mandates, $this->declarations, $this->clock, $this->unitOfWork, $this->policy);
    }

    public function importEmployer(): ImportEmployerHandler
    {
        return new ImportEmployerHandler($this->employerLocator(), $this->accounts, $this->mandates, $this->ids, $this->clock, $this->unitOfWork);
    }

    public function importMandate(): ImportMandateHandler
    {
        return new ImportMandateHandler($this->employerLocator(), $this->mandates, $this->mandateRecorder());
    }

    public function purgeOutbox(): PurgeOutboxHandler
    {
        return new PurgeOutboxHandler($this->outbox, $this->clock, $this->policy);
    }

    public function reconcileStaleDeclarations(): ReconcileStaleDeclarationsHandler
    {
        return new ReconcileStaleDeclarationsHandler($this->declarations, $this->clock, $this->unitOfWork, $this->policy);
    }

    public function recordMandateCancellation(): RecordMandateCancellationHandler
    {
        return new RecordMandateCancellationHandler($this->employerLocator(), $this->mandates, $this->mandateRecorder());
    }

    public function registerMandate(): RegisterMandateHandler
    {
        return new RegisterMandateHandler($this->employerLocator(), $this->mandates, $this->gateway, $this->mandateRecorder());
    }

    /**
     * @throws LogicException when no outbox sink was given (withOutbox())
     */
    public function relayOutbox(): RelayOutboxHandler
    {
        return new RelayOutboxHandler($this->outbox, $this->sink ?? throw new LogicException(self::SINK_MISSING), $this->clock, $this->policy);
    }

    public function requeueAfterInvalidCredentials(): RequeueAfterInvalidCredentialsHandler
    {
        return new RequeueAfterInvalidCredentialsHandler($this->declarations, $this->clock, $this->unitOfWork, $this->policy);
    }

    public function resolveDeclarationOutcome(): ResolveDeclarationOutcomeHandler
    {
        return new ResolveDeclarationOutcomeHandler($this->declarationLocator(), $this->declarations, $this->clock, $this->unitOfWork);
    }

    public function syncEmployerStatus(): SyncEmployerStatusHandler
    {
        return new SyncEmployerStatusHandler($this->employerLocator(), $this->refresher(), $this->accounts, $this->mandates);
    }

    public function syncEmployerStatuses(): SyncEmployerStatusesHandler
    {
        return new SyncEmployerStatusesHandler($this->accounts, $this->refresher(), $this->policy);
    }

    public function trackEmployer(): TrackEmployerHandler
    {
        return new TrackEmployerHandler($this->employerLocator(), $this->accounts, $this->mandates, $this->gateway, $this->ids, $this->clock, $this->unitOfWork);
    }

    public function estimateContributions(): EstimateContributionsHandler
    {
        return new EstimateContributionsHandler($this->gateway);
    }

    public function findDeclarations(): FindDeclarationsHandler
    {
        return new FindDeclarationsHandler($this->declarations, $this->policy);
    }

    public function getDeclaration(): GetDeclarationHandler
    {
        return new GetDeclarationHandler($this->declarationLocator());
    }

    public function getEmployerAccount(): GetEmployerAccountHandler
    {
        return new GetEmployerAccountHandler($this->employerLocator(), $this->mandates);
    }

    /**
     * @throws LogicException when no statistics were given (withRepositories())
     */
    public function getWorkflowOverview(): GetWorkflowOverviewHandler
    {
        return new GetWorkflowOverviewHandler($this->statistics ?? throw new LogicException(self::STATISTICS_MISSING));
    }

    public function preDeclareSalary(): PreDeclareSalaryHandler
    {
        return new PreDeclareSalaryHandler($this->gateway, $this->mandateGuard());
    }

    public function verifyChildren(): VerifyChildrenHandler
    {
        return new VerifyChildrenHandler($this->gateway);
    }

    public function verifyEmployee(): VerifyEmployeeHandler
    {
        return new VerifyEmployeeHandler($this->gateway);
    }

    public function verifyEmployer(): VerifyEmployerHandler
    {
        return new VerifyEmployerHandler($this->gateway, $this->accounts, $this->refresher(), $this->clock);
    }

    private function employerLocator(): EmployerLocator
    {
        return new EmployerLocator($this->accounts);
    }

    private function refresher(): EmployerStatusRefresher
    {
        return new EmployerStatusRefresher($this->gateway, $this->accounts, $this->clock, $this->unitOfWork);
    }

    private function mandateRecorder(): MandateRecorder
    {
        return new MandateRecorder($this->mandates, $this->accounts, $this->ids, $this->clock, $this->unitOfWork);
    }

    private function mandateGuard(): MandateGuard
    {
        return new MandateGuard($this->mandates, $this->policy);
    }

    private function declarationLocator(): DeclarationLocator
    {
        return new DeclarationLocator($this->declarations);
    }

    private function submitter(): DeclarationSubmitter
    {
        return new DeclarationSubmitter($this->declarations, $this->gateway, $this->gateway, $this->clock, $this->unitOfWork, $this->policy);
    }
}
