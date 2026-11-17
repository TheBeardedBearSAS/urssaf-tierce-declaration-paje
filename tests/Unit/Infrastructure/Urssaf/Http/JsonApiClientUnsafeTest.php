<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Http;

use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\TransportError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonObject;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Stack;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;
use Throwable;

/**
 * Status mapping of the client on the operations that are not safe to retry (ADR 0003; "unsafe" column of the
 * lot 3 design section 8).
 *
 * One method per status family.
 *
 * @mago-expect lint:too-many-methods
 */
final class JsonApiClientUnsafeTest extends TestCase
{
    private function send(Stack $stack, Operation $operation): void
    {
        $request = match ($operation) {
            Operation::RegisterMandate => ApiRequest::post($operation, '/mandats', ['numeroPaje' => '1']),
            Operation::CancelMandate => ApiRequest::delete($operation, '/mandats', ['numeroPaje' => '1']),
            Operation::VerifyEmployer,
            Operation::VerifyEmployee,
            Operation::VerifyChildren,
            Operation::AssociateEmployee,
            Operation::EstimateChildminder,
            Operation::EstimateHomeCare,
            Operation::PreDeclareChildminder,
            Operation::PreDeclareHomeCare,
            Operation::DeclareChildminder,
            Operation::DeclareHomeCare => ApiRequest::post($operation, '/p', [], JsonObject::of([])),
        };

        $stack->jsonClient()->send(Stacks::tenant(), $request);
    }

    /**
     * @param array<string, string> $headers
     */
    private function failure(Operation $operation, int $status, string $body = '{}', array $headers = []): Throwable
    {
        return $this->failureWith(
            ApiConfiguration::forEnvironment(Environment::Sandbox),
            $operation,
            $status,
            $body,
            $headers,
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private function failureWith(
        ApiConfiguration $configuration,
        Operation $operation,
        int $status,
        string $body,
        array $headers,
    ): Throwable {
        $stack = Stacks::configured($configuration);
        $stack->warmUpToken();
        $stack->respond($status, $body, $headers);
        // Only consumed by the replay of a 401.
        $stack->respondToken();
        $stack->respond($status, $body, $headers);

        try {
            $this->send($stack, $operation);
        } catch (Throwable $exception) {
            return $exception;
        }

        static::fail(\sprintf('Expected a failure for HTTP %d.', $status));
    }

    /**
     * @return iterable<string, array{Operation}>
     */
    public static function provideUnsafeOperations(): iterable
    {
        yield '023' => [Operation::AssociateEmployee];
        yield '030' => [Operation::RegisterMandate];
        yield '031' => [Operation::CancelMandate];
        yield '050' => [Operation::DeclareChildminder];
        yield '051' => [Operation::DeclareHomeCare];
    }

    /**
     * @return iterable<string, array{Operation}>
     */
    public static function provideSafeWrites(): iterable
    {
        yield '023' => [Operation::AssociateEmployee];
        yield '030' => [Operation::RegisterMandate];
        yield '031' => [Operation::CancelMandate];
    }

    /**
     * @return iterable<string, array{Operation, int}>
     */
    public static function provideUnknownOutcomes(): iterable
    {
        foreach (self::provideUnsafeOperations() as $name => [$operation]) {
            foreach ([500, 501, 502, 503, 504, 505, 511, 513, 599] as $status) {
                yield $name . ' HTTP ' . $status => [$operation, $status];
            }
        }
    }

    #[DataProvider('provideUnknownOutcomes')]
    public function testAnyFiveHundredOtherThan512IsAnUnknownOutcome(Operation $operation, int $status): void
    {
        $exception = $this->failure($operation, $status, Fixtures::load('error_technical'), ['Retry-After' => '9']);

        static::assertInstanceOf(OutcomeUnknown::class, $exception);
        static::assertSame($operation, $exception->operation());
        static::assertSame($status, $exception->httpStatus());
        static::assertSame(['ERR_TECHNIQUE'], $exception->errors()->codes());
        $cause = $exception->getPrevious();
        static::assertInstanceOf(UrssafUnavailable::class, $cause);
        static::assertSame($status, $cause->httpStatus());
        static::assertSame(9, $cause->retryAfterSeconds());
        static::assertTrue($cause->mayHaveReachedUrssaf());
    }

    #[DataProvider('provideUnsafeOperations')]
    public function testATransportErrorIsAnUnknownOutcomeWithASanitisedCause(Operation $operation): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->fail(new TransportFailure('timeout for https://h/employeurs?nir=FICTIVE'));

        try {
            $this->send($stack, $operation);
            static::fail('Expected an unknown outcome.');
        } catch (OutcomeUnknown $exception) {
            static::assertSame($operation, $exception->operation());
            static::assertNull($exception->httpStatus());
            $unavailable = $exception->getPrevious();
            static::assertInstanceOf(UrssafUnavailable::class, $unavailable);
            $previous = $unavailable->getPrevious();
            static::assertInstanceOf(TransportError::class, $previous);
            static::assertStringNotContainsString('FICTIVE', $previous->getMessage());
        }
    }

    #[DataProvider('provideUnsafeOperations')]
    public function testA429IsAnUnavailableQuotaNotAnUnknownOutcome(Operation $operation): void
    {
        $exception = $this->failure($operation, 429, '{}', ['Retry-After' => '30']);

        static::assertInstanceOf(UrssafUnavailable::class, $exception);
        static::assertTrue($exception->isQuotaExceeded());
        static::assertSame(30, $exception->retryAfterSeconds());
        static::assertFalse($exception->mayHaveReachedUrssaf());
    }

    #[DataProvider('provideUnsafeOperations')]
    public function testThe512IsAnAuthenticationFailure(Operation $operation): void
    {
        $exception = $this->failure($operation, 512);

        static::assertInstanceOf(UrssafAuthenticationFailed::class, $exception);
        static::assertSame(512, $exception->httpStatus());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideNotProcessedStatuses(): iterable
    {
        yield 'just below the 5xx range' => [499];
        yield 'informational' => [199];
        yield 'redirect' => [302];
        yield 'not found' => [404];
        yield 'teapot' => [418];
    }

    #[DataProvider('provideNotProcessedStatuses')]
    public function testOtherStatusesAreUnexpectedAndNotUnknown(int $status): void
    {
        $exception = $this->failure(Operation::DeclareChildminder, $status);

        static::assertInstanceOf(UnexpectedUrssafResponse::class, $exception);
        static::assertSame($status, $exception->getCode());
    }

    public function testA400IsARejectionClassifiedForTheOperation(): void
    {
        $exception = $this->failure(Operation::DeclareChildminder, 400, Fixtures::load('error_duplicate_declaration_0070'));

        static::assertInstanceOf(UrssafRequestRejected::class, $exception);
        static::assertSame(Operation::DeclareChildminder, $exception->operation());
        static::assertTrue($exception->is(RejectionKind::DuplicateDeclaration));
    }

    public function testTokenFailuresNeverBecomeAnUnknownOutcome(): void
    {
        foreach ([Operation::DeclareHomeCare, Operation::DeclareChildminder, Operation::AssociateEmployee, Operation::RegisterMandate, Operation::CancelMandate] as $operation) {
            $stack = Stacks::sandbox();
            $stack->respond(500, '');
            try {
                $this->send($stack, $operation);
                static::fail('Expected an unavailable exception.');
            } catch (UrssafUnavailable $exception) {
                static::assertSame(500, $exception->httpStatus());
                static::assertFalse($exception->mayHaveReachedUrssaf());
                static::assertSame($operation, $exception->operation());
            }
            static::assertCount(1, $stack->requests());

            $transport = Stacks::sandbox();
            $transport->fail(new TransportFailure('no route'));
            try {
                $this->send($transport, $operation);
                static::fail('Expected an unavailable exception.');
            } catch (UrssafUnavailable $exception) {
                static::assertNull($exception->httpStatus());
                static::assertFalse($exception->mayHaveReachedUrssaf());
                static::assertInstanceOf(TransportError::class, $exception->getPrevious());
            }
        }
    }

    #[DataProvider('provideSafeWrites')]
    public function testAWriteDeclaredSafeKeepsTheRetryableAndUnexpectedStatuses(Operation $operation): void
    {
        $configuration = new ApiConfiguration(
            Environment::Sandbox,
            null,
            null,
            null,
            60,
            ApiConfiguration::DEFAULT_USER_AGENT,
            new DateTimeZone('Europe/Paris'),
            [$operation],
        );

        foreach ([500, 502, 503, 504] as $status) {
            $exception = $this->failureWith($configuration, $operation, $status, '{}', []);
            static::assertInstanceOf(UrssafUnavailable::class, $exception);
            static::assertSame($status, $exception->httpStatus());
        }
        foreach ([501, 505, 599, 404] as $status) {
            static::assertInstanceOf(
                UnexpectedUrssafResponse::class,
                $this->failureWith($configuration, $operation, $status, '{}', []),
            );
        }

        $stack = Stacks::configured($configuration);
        $stack->warmUpToken();
        $stack->fail(new TransportFailure('timeout'));
        try {
            $this->send($stack, $operation);
            static::fail('Expected an unavailable exception.');
        } catch (UrssafUnavailable $exception) {
            static::assertTrue($exception->mayHaveReachedUrssaf());
        }
    }

    public function testOnlyTheWriteDeclaredSafeIsRetryable(): void
    {
        $configuration = new ApiConfiguration(
            Environment::Sandbox,
            null,
            null,
            null,
            60,
            ApiConfiguration::DEFAULT_USER_AGENT,
            new DateTimeZone('Europe/Paris'),
            [Operation::CancelMandate],
        );

        static::assertInstanceOf(
            UrssafUnavailable::class,
            $this->failureWith($configuration, Operation::CancelMandate, 503, '{}', []),
        );
        static::assertInstanceOf(
            OutcomeUnknown::class,
            $this->failureWith($configuration, Operation::RegisterMandate, 503, '{}', []),
        );
    }
}
