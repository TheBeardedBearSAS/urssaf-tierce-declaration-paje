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

use Closure;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Log\LoggerInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\EventPublisher;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\TokenStore;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\TransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\PajeGateway;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\IgnoredFailures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\IgnoredOutbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineWorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Event\NullEventPublisher;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Id\RandomUuidGenerator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Id\SecureRandomBytes;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryWorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Transaction\ImmediateTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\UrssafGatewayFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\UtcSystemClock;

/**
 * Wires the package without a framework. Every "with" method returns a new builder.
 *
 * Defaults: in-memory repositories, statistics and token store (tests and prototypes: use withDoctrine() or your own
 * repositories, and a shared token store in production), the UTC system clock, random UUIDs, no event publisher, no outbox, the default
 * workflow policy, the HTTP gateway of {@see UrssafGatewayFactory}.
 *
 * One property and one "with" method per collaborator, hence the counts.
 *
 * @mago-expect lint:too-many-properties
 * @mago-expect lint:too-many-methods
 */
final class UrssafTierceDeclarationPajeBuilder
{
    private const GATEWAY_REFUSED_IN_PRODUCTION = 'A custom gateway (a simulator) is refused with the production environment: use the HTTP gateway, or wire the handlers yourself.';

    private ?PajeGateway $gateway = null;
    private TokenStore $tokenStore;
    private Clock $clock;
    private IdGenerator $ids;
    private EventPublisher $events;
    private ?LoggerInterface $logger = null;
    private ?RateLimiter $rateLimiter = null;
    private WorkflowPolicy $policy;
    private EmployerAccountRepository $accounts;
    private MandateRepository $mandates;
    private DeclarationRecordRepository $declarations;
    private TransactionManager $transactions;
    private ?WorkflowStatistics $statistics;
    private Outbox $outbox;
    private ?IntegrationEventSink $sink = null;

    private function __construct(
        private readonly HttpTransport $transport,
        private readonly CredentialsProvider $credentials,
        private readonly ApiConfiguration $configuration,
    ) {
        $this->tokenStore = new InMemoryTokenStore();
        $this->clock = new UtcSystemClock();
        $this->ids = new RandomUuidGenerator(new SecureRandomBytes());
        $this->events = new NullEventPublisher();
        $this->policy = new WorkflowPolicy();
        $accounts = new InMemoryEmployerAccountRepository();
        $mandates = new InMemoryMandateRepository();
        $declarations = new InMemoryDeclarationRecordRepository();
        $this->accounts = $accounts;
        $this->mandates = $mandates;
        $this->declarations = $declarations;
        $this->transactions = new ImmediateTransactionManager();
        $this->statistics = new InMemoryWorkflowStatistics($accounts, $mandates, $declarations);
        $this->outbox = new IgnoredOutbox();
    }

    public static function create(HttpTransport $transport, CredentialsProvider $credentials, ApiConfiguration $configuration): self
    {
        return new self($transport, $credentials, $configuration);
    }

    /**
     * A token store shared by every process that calls URSSAF (PSR-6 / PSR-16 store).
     */
    public function withTokenStore(TokenStore $tokenStore): self
    {
        return $this->with(static function (self $builder) use ($tokenStore): void {
            $builder->tokenStore = $tokenStore;
        });
    }

    public function withClock(Clock $clock): self
    {
        return $this->with(static function (self $builder) use ($clock): void {
            $builder->clock = $clock;
        });
    }

    public function withIdGenerator(IdGenerator $ids): self
    {
        return $this->with(static function (self $builder) use ($ids): void {
            $builder->ids = $ids;
        });
    }

    /**
     * Where the domain events are published after each commit.
     */
    public function withEventPublisher(EventPublisher $events): self
    {
        return $this->with(static function (self $builder) use ($events): void {
            $builder->events = $events;
        });
    }

    /**
     * The logger of the HTTP gateway (never personal data, ADR 0007).
     */
    public function withLogger(LoggerInterface $logger): self
    {
        return $this->with(static function (self $builder) use ($logger): void {
            $builder->logger = $logger;
        });
    }

    /**
     * Replaces the default SlidingWindowRateLimiter of the HTTP gateway (for instance NoopRateLimiter), ADR 0011.
     */
    public function withRateLimiter(RateLimiter $rateLimiter): self
    {
        return $this->with(static function (self $builder) use ($rateLimiter): void {
            $builder->rateLimiter = $rateLimiter;
        });
    }

    public function withPolicy(WorkflowPolicy $policy): self
    {
        return $this->with(static function (self $builder) use ($policy): void {
            $builder->policy = $policy;
        });
    }

    /**
     * Your own persistence; without statistics, getWorkflowOverview() is not available.
     */
    public function withRepositories(
        EmployerAccountRepository $accounts,
        MandateRepository $mandates,
        DeclarationRecordRepository $declarations,
        TransactionManager $transactions,
        ?WorkflowStatistics $statistics,
    ): self {
        return $this->with(static function (self $builder) use ($accounts, $mandates, $declarations, $transactions, $statistics): void {
            $builder->accounts = $accounts;
            $builder->mandates = $mandates;
            $builder->declarations = $declarations;
            $builder->transactions = $transactions;
            $builder->statistics = $statistics;
        });
    }

    /**
     * The Doctrine persistence of the package, on a dedicated entity manager (docs/doctrine.md): repositories,
     * transaction manager and workflow statistics. The outbox is separate: withOutbox(new DoctrineOutbox($entityManager), $sink).
     *
     * ADR 0014.
     */
    public function withDoctrine(EntityManagerInterface $entityManager): self
    {
        return $this->withRepositories(
            new DoctrineEmployerAccountRepository($entityManager),
            new DoctrineMandateRepository($entityManager),
            new DoctrineDeclarationRecordRepository($entityManager),
            new DoctrineTransactionManager($entityManager),
            new DoctrineWorkflowStatistics($entityManager),
        );
    }

    /**
     * Writes the integration events to the outbox (in the transaction of the repositories) and relays them to the sink.
     */
    public function withOutbox(Outbox $outbox, IntegrationEventSink $sink): self
    {
        return $this->with(static function (self $builder) use ($outbox, $sink): void {
            $builder->outbox = $outbox;
            $builder->sink = $sink;
        });
    }

    /**
     * Replaces the HTTP gateway (for instance with the Testing\SimulatedPajeGateway in tests).
     *
     * @throws LogicException with the production environment: nothing but the HTTP gateway may talk for a real employer
     */
    public function withGateway(PajeGateway $gateway): self
    {
        if ($this->configuration->environment() === Environment::Production) {
            throw new LogicException(self::GATEWAY_REFUSED_IN_PRODUCTION);
        }

        return $this->with(static function (self $builder) use ($gateway): void {
            $builder->gateway = $gateway;
        });
    }

    public function build(): UrssafTierceDeclarationPaje
    {
        return new UrssafTierceDeclarationPaje(
            $this->gateway(),
            $this->accounts,
            $this->mandates,
            $this->declarations,
            new UnitOfWork($this->transactions, $this->events, new IgnoredFailures(), $this->outbox),
            $this->clock,
            $this->ids,
            $this->policy,
            $this->statistics,
            $this->outbox,
            $this->sink,
        );
    }

    /**
     * @param Closure(self): void $change applied to a copy of the builder
     */
    private function with(Closure $change): self
    {
        $builder = clone $this;
        $change($builder);

        return $builder;
    }

    private function gateway(): PajeGateway
    {
        return $this->gateway ?? UrssafGatewayFactory::create($this->transport, $this->credentials, $this->tokenStore, $this->configuration, $this->gatewayOptions());
    }

    private function gatewayOptions(): GatewayOptions
    {
        $options = GatewayOptions::defaults()->withClock($this->clock)->withIdGenerator($this->ids);
        if ($this->rateLimiter instanceof RateLimiter) {
            $options = $options->withRateLimiter($this->rateLimiter);
        }

        return $this->logger instanceof LoggerInterface ? $options->withLogger($this->logger) : $options;
    }
}
