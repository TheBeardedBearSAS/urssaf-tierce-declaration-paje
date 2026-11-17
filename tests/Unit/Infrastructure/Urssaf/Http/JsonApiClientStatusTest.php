<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\ApiResponse;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\TransportError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;
use Throwable;

/**
 * Status mapping of the client on a safe operation (020, ADR 0002; "safe" column of the lot 3 design section 8).
 *
 * One method per status family.
 *
 * @mago-expect lint:too-many-methods
 */
final class JsonApiClientStatusTest extends TestCase
{
    /**
     * @param array<string, string> $headers
     */
    private function call(int $status, string $body, array $headers = []): ApiResponse
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond($status, $body, $headers);

        return $stack->jsonClient()->send(
            Stacks::tenant(),
            ApiRequest::get(Operation::VerifyEmployer, '/p', []),
        );
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideSuccessStatuses(): iterable
    {
        yield '200' => [200];
        yield '201' => [201];
        yield '299' => [299];
    }

    #[DataProvider('provideSuccessStatuses')]
    public function testTwoHundredStatusesKeepTheStatusAndTheRawBody(int $status): void
    {
        $response = $this->call($status, '{"k":"v"}');

        static::assertSame($status, $response->status());
        static::assertSame('v', $response->payload()->string('k'));
    }

    public function testASuccessIsNotDecodedByTheClient(): void
    {
        $response = $this->call(200, 'not json');

        static::assertSame(200, $response->status());
        static::assertFalse($response->isEmpty());
    }

    public function testA400IsClassifiedByItsErrorCode(): void
    {
        try {
            $this->call(400, Fixtures::load('error_param_invalide'));
            static::fail('Expected a rejection.');
        } catch (UrssafRequestRejected $exception) {
            static::assertSame(400, $exception->httpStatus());
            static::assertSame(Operation::VerifyEmployer, $exception->operation());
            static::assertSame(RejectionKind::InvalidParameter, $exception->kind());
            static::assertSame(['PARAM_INVALIDE'], $exception->errors()->codes());
        }
    }

    public function testA400WithAnUnreadableBodyIsARejectionWithoutErrors(): void
    {
        try {
            $this->call(400, '<html>Bad Request</html>');
            static::fail('Expected a rejection.');
        } catch (UrssafRequestRejected $exception) {
            static::assertTrue($exception->errors()->isEmpty());
            static::assertSame(RejectionKind::Unknown, $exception->kind());
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideAuthenticationStatuses(): iterable
    {
        yield 'unauthorized after the replay' => [401];
        yield 'forbidden' => [403];
        yield 'security gateway' => [512];
    }

    #[DataProvider('provideAuthenticationStatuses')]
    public function testSecurityStatusesFailAuthentication(int $status): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond($status, '');
        $stack->respondToken();
        $stack->respond($status, '');

        try {
            $stack->jsonClient()->send(
                Stacks::tenant(),
                ApiRequest::get(Operation::VerifyEmployer, '/p', []),
            );
            static::fail('Expected an authentication failure.');
        } catch (UrssafAuthenticationFailed $exception) {
            static::assertSame($status, $exception->httpStatus());
        }
    }

    /**
     * @return iterable<string, array{int, array<string, string>, int|null}>
     */
    public static function provideRetryableStatuses(): iterable
    {
        yield 'quota with seconds' => [429, ['Retry-After' => '12'], 12];
        yield 'quota without header' => [429, [], null];
        yield 'quota with http date' => [429, ['Retry-After' => 'Wed, 21 Oct 2026 07:28:00 GMT'], null];
        yield 'technical error' => [500, [], null];
        yield 'bad gateway' => [502, ['Retry-After' => '1'], 1];
        yield 'unavailable with seconds' => [503, ['Retry-After' => '3'], 3];
        yield 'gateway timeout' => [504, [], null];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('provideRetryableStatuses')]
    public function testQuotaAndListedServerErrorsAreRetryable(int $status, array $headers, ?int $retryAfter): void
    {
        try {
            $this->call($status, Fixtures::load('error_technical'), $headers);
            static::fail('Expected an unavailable exception.');
        } catch (UrssafUnavailable $exception) {
            static::assertSame($status, $exception->httpStatus());
            static::assertSame($retryAfter, $exception->retryAfterSeconds());
            static::assertSame($status === 429, $exception->isQuotaExceeded());
            static::assertSame(Operation::VerifyEmployer, $exception->operation());
            static::assertSame($status !== 429, $exception->mayHaveReachedUrssaf());
            static::assertSame(['ERR_TECHNIQUE'], $exception->errors()->codes());
        }
    }

    public function testAnUnreadableErrorBodyGivesNoErrors(): void
    {
        try {
            $this->call(503, '<html>');
            static::fail('Expected an unavailable exception.');
        } catch (UrssafUnavailable $exception) {
            static::assertTrue($exception->errors()->isEmpty());
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideUnexpectedStatuses(): iterable
    {
        yield 'informational' => [199];
        yield 'multiple choices' => [300];
        yield 'redirect' => [302];
        yield 'payment required' => [402];
        yield 'not found' => [404];
        yield 'method not allowed' => [405];
        yield 'conflict' => [409];
        yield 'teapot' => [418];
        yield 'unprocessable' => [422];
        yield 'not implemented' => [501];
        yield 'http version' => [505];
        yield 'last 5xx' => [599];
    }

    #[DataProvider('provideUnexpectedStatuses')]
    public function testAnyOtherStatusIsUnexpectedOnASafeOperation(int $status): void
    {
        try {
            $this->call($status, '{}');
            static::fail('Expected an unexpected-response exception.');
        } catch (UnexpectedUrssafResponse $exception) {
            static::assertSame($status, $exception->getCode());
            static::assertStringContainsString(\sprintf('HTTP %d', $status), $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{int, class-string<Throwable>}>
     */
    public static function provideServerStatusesOfASafeOperation(): iterable
    {
        yield '500' => [500, UrssafUnavailable::class];
        yield '501' => [501, UnexpectedUrssafResponse::class];
        yield '503' => [503, UrssafUnavailable::class];
        yield '512' => [512, UrssafAuthenticationFailed::class];
        yield '599' => [599, UnexpectedUrssafResponse::class];
    }

    /**
     * @param class-string<Throwable> $expected
     */
    #[DataProvider('provideServerStatusesOfASafeOperation')]
    public function testNoStatusOfASafeOperationBecomesAnUnknownOutcome(int $status, string $expected): void
    {
        try {
            $this->call($status, '{}');
            static::fail('Expected a failure.');
        } catch (Throwable $exception) {
            static::assertNotInstanceOf(OutcomeUnknown::class, $exception);
            static::assertInstanceOf($expected, $exception);
        }
    }

    public function testATransportErrorIsUnavailableWithASanitisedCause(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->fail(new TransportFailure('timeout for https://h/salaries?nir=FICTIVE&nom=FICTIF'));

        try {
            $stack->jsonClient()->send(
                Stacks::tenant(),
                ApiRequest::get(Operation::VerifyEmployee, '/p', []),
            );
            static::fail('Expected an unavailable exception.');
        } catch (UrssafUnavailable $exception) {
            static::assertNull($exception->httpStatus());
            static::assertSame(Operation::VerifyEmployee, $exception->operation());
            static::assertTrue($exception->mayHaveReachedUrssaf());
            $previous = $exception->getPrevious();
            static::assertInstanceOf(TransportError::class, $previous);
            static::assertStringNotContainsString('FICTIVE', $exception->getMessage());
            static::assertStringNotContainsString('FICTIVE', $previous->getMessage());
        }
    }
}
