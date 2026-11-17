<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf;

use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\OperationRetryClass;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\PajeGateway;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\CalculationMode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeCreation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\ChildminderEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\HomeCareEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\YoungestChildAge;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\UrssafGatewayFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\RejectingRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\EmployeeWireSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Stack;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire\DeclarationWireSamples;
use Throwable;

/**
 * Failures of the gateway, per operation class (ADR 0002, ADR 0003, ADR 0005, ADR 0007): the status-level mapping is
 * the client's, the gateway adds the decision on an unreadable 2xx answer.
 *
 * @mago-expect lint:too-many-methods
 */
final class UrssafHttpGatewayFailureTest extends TestCase
{
    /**
     * Calls the operation with valid arguments: its result, null for the mandates.
     */
    private static function call(PajeGateway $gateway, Operation $operation): mixed
    {
        $tenant = Stacks::tenant();
        $employer = EmployeeWireSamples::employer();

        if ($operation === Operation::RegisterMandate) {
            $gateway->registerMandate($tenant, $employer);

            return null;
        }

        if ($operation === Operation::CancelMandate) {
            $gateway->cancelMandate($tenant, $employer);

            return null;
        }

        return match ($operation) {
            Operation::VerifyEmployer => $gateway->verifyEmployer($tenant, $employer),
            Operation::VerifyEmployee => $gateway->verifyEmployee($tenant, EmployeeByNir::of(Nir::of('178010701005265'))),
            Operation::VerifyChildren => $gateway->verifyChildren($tenant, $employer, [EmployeeWireSamples::child()]),
            Operation::AssociateEmployee => $gateway->associateEmployee(
                $tenant,
                $employer,
                EmployeeByNir::of(Nir::of('178010701005265')),
                EmployeeCreation::of(null, null, null, null, null, null),
            ),
            Operation::EstimateChildminder => $gateway->estimateChildminder(
                $tenant,
                ChildminderEstimateRequest::of(null, null, Amount::fromDecimalString('165'), 1, 10, null, null, null),
            ),
            Operation::EstimateHomeCare => $gateway->estimateHomeCare(
                $tenant,
                HomeCareEstimateRequest::of(
                    null,
                    null,
                    Amount::fromDecimalString('165'),
                    CalculationMode::real(),
                    10,
                    0,
                    0,
                    YoungestChildAge::underThree(),
                    true,
                ),
            ),
            Operation::PreDeclareChildminder => $gateway->preDeclareChildminder($tenant, $employer, DeclarationWireSamples::preDeclareChildminder()),
            Operation::PreDeclareHomeCare => $gateway->preDeclareHomeCare($tenant, $employer, DeclarationWireSamples::homeCare()),
            Operation::DeclareChildminder => $gateway->declareChildminder($tenant, $employer, DeclarationWireSamples::declareChildminder()),
            Operation::DeclareHomeCare => $gateway->declareHomeCare($tenant, $employer, DeclarationWireSamples::homeCare()),
        };

    }

    private static function configurationRetrying(Operation ...$operations): ApiConfiguration
    {
        return new ApiConfiguration(
            Environment::Sandbox,
            null,
            null,
            null,
            ApiConfiguration::DEFAULT_TOKEN_SAFETY_MARGIN_SECONDS,
            ApiConfiguration::DEFAULT_USER_AGENT,
            new DateTimeZone(ApiConfiguration::DEFAULT_BUSINESS_TIME_ZONE),
            array_values($operations),
        );
    }

    private static function caught(Stack $stack, Operation $operation): Throwable
    {
        try {
            self::call($stack->gateway(), $operation);
        } catch (Throwable $exception) {
            return $exception;
        }

        static::fail('A failure was expected.');
    }

    /**
     * @param array<string, string> $headers
     */
    private static function failing(Operation $operation, int $status, string $body, array $headers = []): Throwable
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond($status, $body, $headers);

        return self::caught($stack, $operation);
    }

    /**
     * @return iterable<string, array{Operation}>
     */
    public static function everyOperation(): iterable
    {
        foreach (Operation::cases() as $operation) {
            yield $operation->label() => [$operation];
        }
    }

    /**
     * @return iterable<string, array{Operation}>
     */
    public static function safeOperations(): iterable
    {
        foreach (Operation::cases() as $operation) {
            if ($operation->retryClass() !== OperationRetryClass::Safe) {
                continue;
            }

            yield $operation->label() => [$operation];
        }
    }

    /**
     * @return iterable<string, array{Operation}>
     */
    public static function unsafeOperations(): iterable
    {
        foreach (Operation::cases() as $operation) {
            if ($operation->retryClass() === OperationRetryClass::Safe) {
                continue;
            }

            yield $operation->label() => [$operation];
        }
    }

    /**
     * @return iterable<string, array{string, RejectionKind, Operation}>
     */
    public static function rejections(): iterable
    {
        $object = Fixtures::load('error_employer_not_found');
        yield 'object payload' => [$object, RejectionKind::EmployerNotFound, Operation::VerifyEmployer];
        yield 'array payload' => ['[' . $object . ']', RejectionKind::EmployerNotFound, Operation::VerifyEmployer];
        yield 'birth date' => [Fixtures::load('error_birth_date_mismatch'), RejectionKind::BirthDateMismatch, Operation::VerifyChildren];
        yield 'invalid parameter' => [Fixtures::load('error_param_invalide'), RejectionKind::InvalidParameter, Operation::EstimateHomeCare];
        yield 'mandate held elsewhere' => [Fixtures::load('error_compte_deja_mandate'), RejectionKind::MandateHeldElsewhere, Operation::RegisterMandate];
        yield 'mandate missing' => [Fixtures::load('error_mandat_inexistant'), RejectionKind::MandateMissing, Operation::CancelMandate];
        yield 'duplicate declaration' => [Fixtures::load('error_duplicate_declaration_0070'), RejectionKind::DuplicateDeclaration, Operation::DeclareChildminder];
        yield 'duplicate in an array' => ['[' . Fixtures::load('error_duplicate_declaration_0070') . ']', RejectionKind::DuplicateDeclaration, Operation::DeclareHomeCare];
        yield 'unlisted code' => ['{"code":"SOMETHING_NEW","message":"m"}', RejectionKind::Unknown, Operation::PreDeclareHomeCare];
    }

    #[DataProvider('rejections')]
    public function testA400IsClassifiedWhateverThePayloadShape(string $body, RejectionKind $kind, Operation $operation): void
    {
        $rejected = self::failing($operation, 400, $body);

        static::assertInstanceOf(UrssafRequestRejected::class, $rejected);
        static::assertSame($operation, $rejected->operation());
        static::assertSame(400, $rejected->httpStatus());
        static::assertSame($kind, $rejected->kind());
        static::assertTrue($rejected->is($kind));
    }

    public function testARejectionKeepsTheCodeButNotTheBodyInItsMessage(): void
    {
        $rejected = self::failing(Operation::VerifyEmployer, 400, Fixtures::load('error_employer_not_found'));

        static::assertInstanceOf(UrssafRequestRejected::class, $rejected);
        static::assertTrue($rejected->hasErrorCode('ER_API_020_0010'));
        static::assertStringNotContainsString('SI Pajemploi', $rejected->getMessage());
    }

    /**
     * @return iterable<string, array{Operation, string}>
     */
    public static function replayedOperations(): iterable
    {
        yield 'a read' => [Operation::VerifyEmployer, Fixtures::load('verify_employer_response')];
        yield 'a declaration' => [Operation::DeclareChildminder, '{}'];
        yield 'a mandate' => [Operation::RegisterMandate, ''];
    }

    #[DataProvider('replayedOperations')]
    public function testA401IsReplayedOnceWithANewToken(Operation $operation, string $answer): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(401, '');
        $stack->respondToken();
        $stack->respond(200, $answer);

        self::call($stack->gateway(), $operation);

        static::assertCount(4, $stack->requests());
        static::assertCount(2, $stack->rateLimiter->acquired());
    }

    public function testASecond401IsAnAuthenticationFailure(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(401, '');
        $stack->respondToken();
        $stack->respond(401, '');

        $failure = self::caught($stack, Operation::DeclareChildminder);

        static::assertInstanceOf(UrssafAuthenticationFailed::class, $failure);
        static::assertSame(401, $failure->httpStatus());
    }

    #[DataProvider('everyOperation')]
    public function testA429IsRetryableOnEveryOperationWithItsDelay(Operation $operation): void
    {
        $unavailable = self::failing($operation, 429, '{}', ['Retry-After' => '30']);

        static::assertInstanceOf(UrssafUnavailable::class, $unavailable);
        static::assertSame($operation, $unavailable->operation());
        static::assertSame(429, $unavailable->httpStatus());
        static::assertSame(30, $unavailable->retryAfterSeconds());
        static::assertTrue($unavailable->isQuotaExceeded());
        static::assertFalse($unavailable->mayHaveReachedUrssaf());
    }

    #[DataProvider('safeOperations')]
    public function testA5xxOnASafeOperationIsRetryable(Operation $operation): void
    {
        $unavailable = self::failing($operation, 503, Fixtures::load('error_technical'));

        static::assertInstanceOf(UrssafUnavailable::class, $unavailable);
        static::assertSame(503, $unavailable->httpStatus());
        static::assertTrue($unavailable->mayHaveReachedUrssaf());
    }

    #[DataProvider('unsafeOperations')]
    public function testA5xxOnAWriteThatMayHaveBeenProcessedIsAnUnknownOutcome(Operation $operation): void
    {
        $unknown = self::failing($operation, 500, Fixtures::load('error_technical'));

        static::assertInstanceOf(OutcomeUnknown::class, $unknown);
        static::assertSame($operation, $unknown->operation());
        static::assertSame(OutcomeUnknownReason::ServerError, $unknown->reason());
        static::assertSame(500, $unknown->httpStatus());
    }

    #[DataProvider('safeOperations')]
    public function testATransportFailureOnASafeOperationIsRetryable(Operation $operation): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->fail(new TransportFailure('timeout for https://h/employeurs?nir=FICTIVE'));

        $unavailable = self::caught($stack, $operation);

        static::assertInstanceOf(UrssafUnavailable::class, $unavailable);
        static::assertNull($unavailable->httpStatus());
        static::assertStringNotContainsString('FICTIVE', $unavailable->getMessage());
    }

    #[DataProvider('unsafeOperations')]
    public function testATransportFailureOnAWriteIsAnUnknownOutcome(Operation $operation): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->fail(new TransportFailure('timeout'));

        $unknown = self::caught($stack, $operation);

        static::assertInstanceOf(OutcomeUnknown::class, $unknown);
        static::assertSame(OutcomeUnknownReason::Transport, $unknown->reason());
        static::assertNull($unknown->httpStatus());
    }

    public function testALocalRateLimitRefusalSendsNothing(): void
    {
        $stack = Stacks::sandbox();

        try {
            UrssafGatewayFactory::create(
                $stack->transport(),
                $stack->credentials,
                $stack->tokens,
                $stack->configuration,
                $stack->optionsWith(new RejectingRateLimiter()),
            )->declareChildminder(
                Stacks::tenant(),
                EmployeeWireSamples::employer(),
                DeclarationWireSamples::declareChildminder(),
            );
            static::fail('A refusal was expected.');
        } catch (RateLimitExceeded $exceeded) {
            static::assertSame(Operation::DeclareChildminder, $exceeded->operation());
        }

        static::assertSame([], $stack->requests());
    }

    /**
     * @return iterable<string, array{Operation, string}>
     */
    public static function unreadableDeclarations(): iterable
    {
        foreach ([Operation::DeclareChildminder, Operation::DeclareHomeCare] as $operation) {
            yield $operation->label() . ': not JSON' => [$operation, 'not json'];
            yield $operation->label() . ': empty body' => [$operation, ''];
            yield $operation->label() . ': a list' => [$operation, '[]'];
            yield $operation->label() . ': a wrong type' => [$operation, '{"referenceDocumentaire":42}'];
            yield $operation->label() . ': an invalid date' => [$operation, Fixtures::load('declare_response')];
        }
    }

    #[DataProvider('unreadableDeclarations')]
    public function testAnUnreadableSuccessOnADeclarationIsAnUnknownOutcome(Operation $operation, string $body): void
    {
        $unknown = self::failing($operation, 200, $body);

        static::assertInstanceOf(OutcomeUnknown::class, $unknown);
        static::assertSame($operation, $unknown->operation());
        static::assertSame(OutcomeUnknownReason::UnreadableResponse, $unknown->reason());
        static::assertSame(200, $unknown->httpStatus());
        static::assertInstanceOf(UnexpectedUrssafResponse::class, $unknown->getPrevious());
        static::assertStringNotContainsString('referenceDocumentaire', $unknown->getMessage());
    }

    public function testTheStatusOfAnUnreadableSuccessIsKept(): void
    {
        $unknown = self::failing(Operation::DeclareHomeCare, 201, 'x');

        static::assertInstanceOf(OutcomeUnknown::class, $unknown);
        static::assertSame(201, $unknown->httpStatus());
    }

    /**
     * @return iterable<string, array{Operation, string}>
     */
    public static function unreadableSafeAnswers(): iterable
    {
        yield 'verify employer, not JSON' => [Operation::VerifyEmployer, 'not json'];
        yield 'verify employee, wrong type' => [Operation::VerifyEmployee, '{"indcAma":"x"}'];
        yield 'verify children, a list' => [Operation::VerifyChildren, '[]'];
        yield 'estimate, empty body' => [Operation::EstimateChildminder, ''];
        yield 'estimate GED, wrong type' => [Operation::EstimateHomeCare, '{"mntExoEmployeur":"x"}'];
        yield 'pre-declaration, invalid date' => [Operation::PreDeclareChildminder, Fixtures::load('predeclare_response')];
        yield 'pre-declaration GED, not JSON' => [Operation::PreDeclareHomeCare, 'not json'];
    }

    #[DataProvider('unreadableSafeAnswers')]
    public function testAnUnreadableSuccessOnASafeOperationIsAnUnexpectedResponse(Operation $operation, string $body): void
    {
        $unexpected = self::failing($operation, 200, $body);

        static::assertInstanceOf(UnexpectedUrssafResponse::class, $unexpected);
    }

    /**
     * @return iterable<string, array{Operation, string}>
     */
    public static function unreadableUnspecifiedWrites(): iterable
    {
        yield 'associate, not JSON' => [Operation::AssociateEmployee, 'not json'];
        yield 'associate, a list' => [Operation::AssociateEmployee, '[]'];
        yield 'register mandate, a payload' => [Operation::RegisterMandate, '{"a":1}'];
        yield 'register mandate, a list' => [Operation::RegisterMandate, '[]'];
        yield 'register mandate, not JSON' => [Operation::RegisterMandate, 'ok'];
        yield 'cancel mandate, a payload' => [Operation::CancelMandate, '{"a":1}'];
        yield 'cancel mandate, a list' => [Operation::CancelMandate, '[1]'];
    }

    #[DataProvider('unreadableUnspecifiedWrites')]
    public function testAnUnreadableSuccessOnAnUnlistedWriteIsAnUnknownOutcome(Operation $operation, string $body): void
    {
        $unknown = self::failing($operation, 200, $body);

        static::assertInstanceOf(OutcomeUnknown::class, $unknown);
        static::assertSame(OutcomeUnknownReason::UnreadableResponse, $unknown->reason());
        static::assertSame(200, $unknown->httpStatus());
        static::assertStringNotContainsString('"a"', $unknown->getMessage());
    }

    #[DataProvider('unreadableUnspecifiedWrites')]
    public function testAnUnreadableSuccessOnAListedWriteIsAnUnexpectedResponse(Operation $operation, string $body): void
    {
        $stack = Stacks::configured(self::configurationRetrying(
            Operation::AssociateEmployee,
            Operation::RegisterMandate,
            Operation::CancelMandate,
        ));
        $stack->warmUpToken();
        $stack->respond(200, $body);

        $unexpected = self::caught($stack, $operation);

        static::assertInstanceOf(UnexpectedUrssafResponse::class, $unexpected);
    }

    public function testAListedWriteIsRetryableAfterAServerError(): void
    {
        $stack = Stacks::configured(self::configurationRetrying(Operation::RegisterMandate));
        $stack->warmUpToken();
        $stack->respond(500, Fixtures::load('error_technical'));
        $stack->respond(500, Fixtures::load('error_technical'));

        $listed = self::caught($stack, Operation::RegisterMandate);
        $unlisted = self::caught($stack, Operation::CancelMandate);

        static::assertInstanceOf(UrssafUnavailable::class, $listed);
        static::assertInstanceOf(OutcomeUnknown::class, $unlisted);
    }

    public function testAnAcceptedEmptyAnswerOnTheMandatesIsNotAFailure(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, " \n");
        $stack->respond(201, '{}');
        $gateway = $stack->gateway();

        $gateway->registerMandate(Stacks::tenant(), EmployeeWireSamples::employer());
        $gateway->cancelMandate(Stacks::tenant(), EmployeeWireSamples::employer());

        static::assertCount(3, $stack->requests());
    }

    public function testAnEmptyDeclarationAnswerIsARecordedDeclaration(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, '{}');

        $result = $stack->gateway()->declareChildminder(
            Stacks::tenant(),
            EmployeeWireSamples::employer(),
            DeclarationWireSamples::declareChildminder(),
        );

        static::assertNull($result->documentReference());
    }
}
