<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Standalone;

use Http\Mock\Client;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\DeclareSalary;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ImportEmployer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\PurgeOutbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ReconcileStaleDeclarations;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RegisterMandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RelayOutbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ResolveDeclarationOutcome;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\SyncEmployerStatuses;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\TrackEmployer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\MandateRequired;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\FindDeclarations;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetEmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetWorkflowOverview;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\PreDeclareSalary;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\VerifyEmployer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineOutbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Event\InMemoryEventPublisher;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Outbox\InMemoryOutbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Transaction\ImmediateTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\UrssafHttpGateway;
use TheBeardedBear\UrssafTierceDeclarationPaje\Standalone\UrssafTierceDeclarationPaje;
use TheBeardedBear\UrssafTierceDeclarationPaje\Standalone\UrssafTierceDeclarationPajeBuilder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Testing\SimulatedPajeGateway;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support\TestDatabase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\FixedClock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\RecordingIntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\SequentialIdGenerator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\RejectingRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Stack;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Testing\SimulatorSamples;

/**
 * The standalone wiring end to end, over the simulator or over a PSR-18 mock client. Fictitious values only.
 *
 * @mago-expect lint:too-many-methods
 */
final class UrssafTierceDeclarationPajeBuilderTest extends TestCase
{
    private const NOW = '2026-11-17T09:00:00Z';

    public function testEveryUseCaseIsAvailable(): void
    {
        $package = self::builder()
            ->withGateway(new SimulatedPajeGateway())
            ->withOutbox(new InMemoryOutbox(), new RecordingIntegrationEventSink())
            ->build();
        $accessors = [];
        foreach ((new ReflectionClass(UrssafTierceDeclarationPaje::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $type = $method->getReturnType();
            if ($type instanceof ReflectionNamedType && !$method->isConstructor() && $method->getName() !== 'gateway') {
                $accessors[$type->getName()] = $method;
            }
        }

        $handlers = [];
        foreach (['Command', 'Query'] as $kind) {
            $files = glob(__DIR__ . '/../../../src/Application/' . $kind . '/*Handler.php');
            static::assertIsArray($files);
            foreach ($files as $file) {
                $handlers[] = 'TheBeardedBear\\UrssafTierceDeclarationPaje\\Application\\' . $kind . '\\' . basename($file, '.php');
            }
        }

        static::assertCount(25, $handlers);
        static::assertCount(\count($handlers), $accessors);
        foreach ($handlers as $handler) {
            static::assertArrayHasKey($handler, $accessors, $handler . ' has no accessor.');
            static::assertSame($handler, get_debug_type($accessors[$handler]->invoke($package)));
        }
        static::assertInstanceOf(SimulatedPajeGateway::class, $package->gateway());
    }

    public function testTheWholeWorkflowRunsWithTheSimulator(): void
    {
        $gateway = SimulatorSamples::gateway();
        $package = self::builder()->withGateway($gateway)->build();
        $tenant = SimulatorSamples::tenant();
        $employerRef = ExternalReference::fromString('employer-1');

        $tracked = $package->trackEmployer()(new TrackEmployer($tenant, $employerRef, SimulatorSamples::employer()));
        $mandate = $package->registerMandate()(new RegisterMandate($tenant, EmployerSelector::byExternalReference($employerRef)));
        $status = $package->verifyEmployer()(new VerifyEmployer($tenant, SimulatorSamples::employer()));
        $pre = $package->preDeclareSalary()(new PreDeclareSalary($tenant, SimulatorSamples::employer(), SimulatorSamples::childminder()));
        $declared = $package->declareSalary()(new DeclareSalary($tenant, ExternalReference::fromString('payslip-1'), SimulatorSamples::employer(), SimulatorSamples::childminder()));
        $again = $package->declareSalary()(new DeclareSalary($tenant, ExternalReference::fromString('payslip-2'), SimulatorSamples::employer(), SimulatorSamples::childminder()));
        $found = $package->getDeclaration()(new GetDeclaration($tenant, DeclarationSelector::byExternalReference(ExternalReference::fromString('payslip-1'))));
        $listed = $package->findDeclarations()(FindDeclarations::inState($tenant, DeclarationState::Declared, null));
        $account = $package->getEmployerAccount()(new GetEmployerAccount($tenant, EmployerSelector::byExternalReference($employerRef)));
        $overview = $package->getWorkflowOverview()(GetWorkflowOverview::forTenant($tenant));

        static::assertSame('tracked', $tracked->stage);
        static::assertSame('registered', $mandate->state);
        static::assertTrue($gateway->isMandated(SimulatorSamples::employerNumber(), $tenant));
        static::assertTrue($status->authorised);
        static::assertSame(ItemOutcome::Declared, $declared->outcome);
        static::assertSame('declared', $declared->declaration->status->state);
        static::assertSame('SIM-DECL-000001', $declared->declaration->status->result?->documentReference);
        static::assertSame('SIM-DECL-000001', $found->status->result?->documentReference);
        static::assertSame(ItemOutcome::AlreadyDeclared, $again->outcome);
        static::assertSame('already_declared', $again->declaration->status->state);
        static::assertCount(1, $listed);
        static::assertSame('mandate_registered', $account->stage);
        static::assertNotSame([], $overview);
        static::assertNotNull($pre->amounts);
        static::assertSame(
            [
                Operation::VerifyEmployer,
                Operation::RegisterMandate,
                Operation::VerifyEmployer,
                Operation::PreDeclareChildminder,
                Operation::DeclareChildminder,
                Operation::DeclareChildminder,
            ],
            $gateway->calls(),
        );
    }

    public function testAnUnknownOutcomeIsResolvedByAnOperator(): void
    {
        $gateway = SimulatorSamples::gateway();
        $gateway->loseNextAnswer(Operation::DeclareChildminder);
        $package = self::builder()->withGateway($gateway)->build();
        $tenant = SimulatorSamples::tenant();
        $reference = ExternalReference::fromString('payslip-1');

        $unknown = $package->declareSalary()(new DeclareSalary($tenant, $reference, SimulatorSamples::employer(), SimulatorSamples::childminder()));
        $resolved = $package->resolveDeclarationOutcome()(ResolveDeclarationOutcome::asDeclared($tenant, DeclarationSelector::byExternalReference($reference), 'SIM-DECL-000001'));

        static::assertSame(ItemOutcome::OutcomeUnknown, $unknown->outcome);
        static::assertSame('outcome_unknown', $unknown->declaration->status->state);
        static::assertSame('declared', $resolved->status->state);
        static::assertSame('SIM-DECL-000001', $resolved->status->result?->documentReference);
        static::assertCount(1, $gateway->calls());
    }

    public function testTheBatchUseCasesRunOnAnEmptyStore(): void
    {
        $package = self::builder()->withGateway(new SimulatedPajeGateway())->build();

        $sync = $package->syncEmployerStatuses()(new SyncEmployerStatuses(SimulatorSamples::tenant(), null));
        $stale = $package->reconcileStaleDeclarations()(new ReconcileStaleDeclarations(null));
        $purged = $package->purgeOutbox()(new PurgeOutbox(null));

        static::assertSame(0, $sync->total());
        static::assertSame(0, $stale->total());
        static::assertSame(0, $purged);
    }

    public function testThePolicyIsUsed(): void
    {
        $gateway = SimulatorSamples::gateway();
        $strict = self::builder()->withGateway($gateway)->withPolicy((new WorkflowPolicy())->withRequireLocalMandate(true))->build();
        $lenient = self::builder()->withGateway($gateway)->build();
        $command = new DeclareSalary(SimulatorSamples::tenant(), ExternalReference::fromString('payslip-1'), SimulatorSamples::employer(), SimulatorSamples::childminder());

        try {
            $strict->declareSalary()($command);
            static::fail('The mandate guard must refuse the declaration.');
        } catch (MandateRequired) {
            static::assertSame([], $gateway->calls());
        }

        static::assertSame(ItemOutcome::Declared, $lenient->declareSalary()($command)->outcome);
    }

    public function testTheHttpGatewayIsBuiltFromTheTransportAndItsOptions(): void
    {
        $http = new Client();
        $http->addResponse(new Response(200, [], Stack::TOKEN_RESPONSE));
        $http->addResponse(new Response(200, [], Fixtures::load('verify_employer_response')));
        $logger = new CapturingLogger();
        $package = self::builderOver($http)
            ->withClock(new FixedClock(self::NOW))
            ->withIdGenerator(new SequentialIdGenerator())
            ->withLogger($logger)
            ->build();

        $status = $package->verifyEmployer()(new VerifyEmployer(SimulatorSamples::tenant(), SimulatorSamples::employer()));

        static::assertInstanceOf(UrssafHttpGateway::class, $package->gateway());
        static::assertTrue($status->authorised);
        $requests = $http->getRequests();
        static::assertCount(2, $requests);
        static::assertSame('GET', $requests[1]->getMethod());
        static::assertSame('/tiersdecl/v1/paje/employeurs/' . SimulatorSamples::EMPLOYER_NUMBER . '/verifier', $requests[1]->getUri()->getPath());
        static::assertSame('dtNaissanceEmployeur=' . SimulatorSamples::BIRTH_DATE, $requests[1]->getUri()->getQuery());
        static::assertNotSame([], $logger->records());
    }

    public function testTheRateLimiterAndTheTokenStoreAreUsed(): void
    {
        $limited = new Client();
        $package = self::builderOver($limited)->withRateLimiter(new RejectingRateLimiter())->build();
        $query = new VerifyEmployer(SimulatorSamples::tenant(), SimulatorSamples::employer());

        try {
            $package->verifyEmployer()($query);
            static::fail('The rate limiter must refuse the call.');
        } catch (RateLimitExceeded) {
            static::assertSame([], $limited->getRequests());
        }

        $tokens = new InMemoryTokenStore();
        $first = new Client();
        $first->addResponse(new Response(200, [], Stack::TOKEN_RESPONSE));
        $first->addResponse(new Response(200, [], Fixtures::load('verify_employer_response')));
        $second = new Client();
        $second->addResponse(new Response(200, [], Fixtures::load('verify_employer_response')));

        self::builderOver($first)->withClock(new FixedClock(self::NOW))->withTokenStore($tokens)->build()->verifyEmployer()($query);
        self::builderOver($second)->withClock(new FixedClock(self::NOW))->withTokenStore($tokens)->build()->verifyEmployer()($query);

        static::assertCount(2, $first->getRequests());
        static::assertCount(1, $second->getRequests());
    }

    public function testTheEventPublisherReceivesTheDomainEvents(): void
    {
        $publisher = new InMemoryEventPublisher();
        $package = self::builder()->withGateway(new SimulatedPajeGateway())->withEventPublisher($publisher)->build();

        $package->importEmployer()(new ImportEmployer(SimulatorSamples::tenant(), ExternalReference::fromString('employer-1'), SimulatorSamples::employer()));

        static::assertNotSame([], $publisher->published());
    }

    public function testTheOutboxIsWrittenAndRelayed(): void
    {
        $outbox = new InMemoryOutbox();
        $sink = new RecordingIntegrationEventSink();
        $package = self::builder()->withGateway(SimulatorSamples::gateway())->withOutbox($outbox, $sink)->build();
        $reference = ExternalReference::fromString('employer-1');
        $package->importEmployer()(new ImportEmployer(SimulatorSamples::tenant(), $reference, SimulatorSamples::employer()));
        $package->registerMandate()(new RegisterMandate(SimulatorSamples::tenant(), EmployerSelector::byExternalReference($reference)));
        $pending = $outbox->pending(10);

        $report = $package->relayOutbox()(new RelayOutbox(null));

        static::assertNotSame([], $pending);
        static::assertSame([], $outbox->pending(10));
        static::assertCount(\count($pending), $sink->deliveredIds());
        static::assertSame(\count($pending), $report->dispatched);
    }

    public function testCustomRepositoriesAreUsed(): void
    {
        $accounts = new InMemoryEmployerAccountRepository();
        $package = self::builder()
            ->withGateway(new SimulatedPajeGateway())
            ->withRepositories($accounts, new InMemoryMandateRepository(), new InMemoryDeclarationRecordRepository(), new ImmediateTransactionManager(), null)
            ->build();

        $package->importEmployer()(new ImportEmployer(SimulatorSamples::tenant(), ExternalReference::fromString('employer-1'), SimulatorSamples::employer()));

        static::assertNotNull($accounts->findByExternalReference(SimulatorSamples::tenant(), ExternalReference::fromString('employer-1')));
    }

    public function testTheDoctrinePersistenceIsUsedWithItsStatisticsAndItsOutbox(): void
    {
        $entityManager = TestDatabase::entityManager();
        $sink = new RecordingIntegrationEventSink();
        $package = self::builder()
            ->withGateway(SimulatorSamples::gateway())
            ->withDoctrine($entityManager)
            ->withOutbox(new DoctrineOutbox($entityManager), $sink)
            ->build();
        $tenant = SimulatorSamples::tenant();
        $reference = ExternalReference::fromString('employer-1');

        $imported = $package->importEmployer()(new ImportEmployer($tenant, $reference, SimulatorSamples::employer()));
        $package->registerMandate()(new RegisterMandate($tenant, EmployerSelector::byExternalReference($reference)));
        $entityManager->clear();

        $stored = (new DoctrineEmployerAccountRepository($entityManager))->findByExternalReference($tenant, $reference);
        static::assertNotNull($stored);
        static::assertSame($imported->accountId, $stored->id()->value());
        static::assertSame(1, $package->getWorkflowOverview()(GetWorkflowOverview::forTenant($tenant))[0]->mandates['registered']);
        $relay = $package->relayOutbox()(new RelayOutbox(null));
        static::assertGreaterThan(0, $relay->dispatched);
        static::assertCount($relay->dispatched, $sink->deliveredIds());
    }

    public function testTheOverviewNeedsStatistics(): void
    {
        $package = self::builder()
            ->withRepositories(new InMemoryEmployerAccountRepository(), new InMemoryMandateRepository(), new InMemoryDeclarationRecordRepository(), new ImmediateTransactionManager(), null)
            ->build();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No WorkflowStatistics');

        $package->getWorkflowOverview();
    }

    public function testTheRelayNeedsASink(): void
    {
        $package = self::builder()->build();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No IntegrationEventSink');

        $package->relayOutbox();
    }

    public function testASimulatorIsRefusedInProduction(): void
    {
        $factory = new Psr17Factory();
        $builder = UrssafTierceDeclarationPajeBuilder::create(
            new HttpTransport(new Client(), $factory, $factory),
            new StaticCredentialsProvider(),
            ApiConfiguration::forEnvironment(Environment::Production),
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('refused with the production environment');

        $builder->withGateway(new SimulatedPajeGateway());
    }

    public function testTheHttpGatewayIsBuiltInProduction(): void
    {
        $factory = new Psr17Factory();
        $package = UrssafTierceDeclarationPajeBuilder::create(
            new HttpTransport(new Client(), $factory, $factory),
            new StaticCredentialsProvider(),
            ApiConfiguration::forEnvironment(Environment::Production),
        )->build();

        static::assertInstanceOf(UrssafHttpGateway::class, $package->gateway());
    }

    public function testABuilderIsNeverModified(): void
    {
        $base = self::builder();
        $derived = $base->withGateway(new SimulatedPajeGateway());

        static::assertInstanceOf(UrssafHttpGateway::class, $base->build()->gateway());
        static::assertInstanceOf(SimulatedPajeGateway::class, $derived->build()->gateway());
        static::assertNotSame($base, $derived);
    }

    private static function builder(): UrssafTierceDeclarationPajeBuilder
    {
        return self::builderOver(new Client());
    }

    private static function builderOver(Client $http): UrssafTierceDeclarationPajeBuilder
    {
        $factory = new Psr17Factory();

        return UrssafTierceDeclarationPajeBuilder::create(
            new HttpTransport($http, $factory, $factory),
            new StaticCredentialsProvider(),
            ApiConfiguration::forEnvironment(Environment::Sandbox),
        );
    }
}
