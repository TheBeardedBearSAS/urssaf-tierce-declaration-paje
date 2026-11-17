<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Port\Urssaf;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RetryableException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\UrssafException;

/**
 * One method per behaviour of the gateway exceptions.
 *
 * @mago-expect lint:too-many-methods
 */
final class GatewayExceptionsTest extends TestCase
{
    public function testRequestRejected(): void
    {
        $errors = ApiErrors::of(ApiError::of('ER_API_050_0070', 'm', 'd'), ApiError::of('PARAM_INVALIDE', 'm', null));

        $exception = UrssafRequestRejected::withErrors(
            Operation::DeclareChildminder,
            400,
            $errors,
            RejectionKind::DuplicateDeclaration,
        );

        static::assertSame(
            'URSSAF method 050 rejected the request (HTTP 400, duplicate_declaration): ER_API_050_0070, PARAM_INVALIDE.',
            $exception->getMessage(),
        );
        static::assertSame(Operation::DeclareChildminder, $exception->operation());
        static::assertSame(400, $exception->httpStatus());
        static::assertSame(400, $exception->getCode());
        static::assertSame($errors, $exception->errors());
        static::assertTrue($exception->hasErrorCode('PARAM_INVALIDE'));
        static::assertFalse($exception->hasErrorCode('ERR_TECHNIQUE'));
        static::assertSame(RejectionKind::DuplicateDeclaration, $exception->kind());
        static::assertTrue($exception->is(RejectionKind::DuplicateDeclaration));
        static::assertFalse($exception->is(RejectionKind::Unknown));
        static::assertContains(UrssafException::class, class_implements($exception));
        static::assertNotContains(RetryableException::class, class_implements($exception));
    }

    public function testRequestRejectedMessageNeverCarriesADescription(): void
    {
        $errors = ApiErrors::of(ApiError::of('PARAM_INVALIDE', 'La valeur 1990-11-256 est incorrecte', 'La valeur 1990-11-256 est incorrecte'));

        $exception = UrssafRequestRejected::withErrors(
            Operation::VerifyEmployer,
            400,
            $errors,
            RejectionKind::InvalidParameter,
        );

        static::assertStringNotContainsString('1990', $exception->getMessage());
    }

    public function testRequestRejectedWithoutErrors(): void
    {
        $exception = UrssafRequestRejected::withErrors(
            Operation::EstimateChildminder,
            400,
            ApiErrors::none(),
            RejectionKind::Unknown,
        );

        static::assertSame(
            'URSSAF method 040 rejected the request (HTTP 400, unknown): no error code.',
            $exception->getMessage(),
        );
    }

    public function testAuthenticationFailed(): void
    {
        $exception = UrssafAuthenticationFailed::withStatus(401);

        static::assertSame('URSSAF authentication or authorisation failed (HTTP 401).', $exception->getMessage());
        static::assertSame(401, $exception->httpStatus());
        static::assertSame(401, $exception->getCode());
        static::assertContains(UrssafException::class, class_implements($exception));
        static::assertNotContains(RetryableException::class, class_implements($exception));
    }

    public function testUnavailableWithStatus(): void
    {
        $exception = UrssafUnavailable::withStatus(Operation::DeclareChildminder, 429, 30, ApiErrors::none());

        static::assertSame('URSSAF method 050 is temporarily unavailable (HTTP 429).', $exception->getMessage());
        static::assertSame(Operation::DeclareChildminder, $exception->operation());
        static::assertSame(429, $exception->httpStatus());
        static::assertSame(429, $exception->getCode());
        static::assertSame(30, $exception->retryAfterSeconds());
        static::assertTrue($exception->isQuotaExceeded());
        static::assertNull($exception->getPrevious());
        static::assertFalse($exception->mayHaveReachedUrssaf());
        static::assertFalse($exception->whileAuthenticating());
        static::assertTrue($exception->errors()->isEmpty());
        static::assertContains(RetryableException::class, class_implements($exception));
    }

    /**
     * @return iterable<string, array{int, bool, bool}>
     */
    public static function provideStatuses(): iterable
    {
        yield '428' => [428, false, false];
        yield '429' => [429, true, false];
        yield '430' => [430, false, false];
        yield '499' => [499, false, false];
        yield '500' => [500, false, true];
        yield '503' => [503, false, true];
    }

    #[DataProvider('provideStatuses')]
    public function testUnavailableStatusBoundaries(int $status, bool $isQuota, bool $mayHaveReached): void
    {
        $exception = UrssafUnavailable::withStatus(Operation::VerifyEmployer, $status, null, ApiErrors::none());

        static::assertSame($isQuota, $exception->isQuotaExceeded());
        static::assertSame($mayHaveReached, $exception->mayHaveReachedUrssaf());
        static::assertNull($exception->retryAfterSeconds());
    }

    public function testUnavailableKeepsTheErrorsOfATechnicalAnswer(): void
    {
        $errors = ApiErrors::of(ApiError::of('ERR_TECHNIQUE', 'm', null));

        $exception = UrssafUnavailable::withStatus(Operation::VerifyEmployer, 500, null, $errors);

        static::assertSame($errors, $exception->errors());
    }

    public function testUnavailableDueToTransportError(): void
    {
        $previous = new RuntimeException('boom');

        $exception = UrssafUnavailable::dueToTransportError(Operation::DeclareHomeCare, $previous);

        static::assertSame('URSSAF method 051 could not be reached.', $exception->getMessage());
        static::assertNull($exception->httpStatus());
        static::assertSame(0, $exception->getCode());
        static::assertNull($exception->retryAfterSeconds());
        static::assertFalse($exception->isQuotaExceeded());
        static::assertTrue($exception->mayHaveReachedUrssaf());
        static::assertFalse($exception->whileAuthenticating());
        static::assertSame($previous, $exception->getPrevious());
        static::assertTrue($exception->errors()->isEmpty());
    }

    public function testUnavailableDuringAuthentication(): void
    {
        $previous = new RuntimeException('boom');

        $exception = UrssafUnavailable::duringAuthentication(Operation::DeclareChildminder, 503, 7, $previous);

        static::assertSame(
            'URSSAF authentication for method 050 is temporarily unavailable.',
            $exception->getMessage(),
        );
        static::assertSame(503, $exception->httpStatus());
        static::assertSame(7, $exception->retryAfterSeconds());
        static::assertFalse($exception->mayHaveReachedUrssaf());
        static::assertTrue($exception->whileAuthenticating());
        static::assertFalse($exception->isQuotaExceeded());
        static::assertSame($previous, $exception->getPrevious());
    }

    public function testUnavailableDuringAuthenticationWithoutStatus(): void
    {
        $exception = UrssafUnavailable::duringAuthentication(Operation::VerifyEmployer, null, null, null);

        static::assertNull($exception->httpStatus());
        static::assertSame(0, $exception->getCode());
        static::assertNull($exception->getPrevious());
    }

    public function testOutcomeUnknownFromATransportError(): void
    {
        $cause = UrssafUnavailable::dueToTransportError(Operation::DeclareChildminder, new RuntimeException('boom'));

        $exception = OutcomeUnknown::fromUnavailable($cause);

        static::assertSame(
            'The outcome of URSSAF method 050 is unknown (transport): the call may have been processed and must not be resent automatically.',
            $exception->getMessage(),
        );
        static::assertSame(Operation::DeclareChildminder, $exception->operation());
        static::assertSame(OutcomeUnknownReason::Transport, $exception->reason());
        static::assertNull($exception->httpStatus());
        static::assertSame(0, $exception->getCode());
        static::assertTrue($exception->errors()->isEmpty());
        static::assertSame($cause, $exception->getPrevious());
        static::assertContains(UrssafException::class, class_implements($exception));
        static::assertNotContains(RetryableException::class, class_implements($exception));
    }

    public function testOutcomeUnknownFromAServerError(): void
    {
        $errors = ApiErrors::of(ApiError::of('ERR_TECHNIQUE', 'm', null));
        $cause = UrssafUnavailable::withStatus(Operation::DeclareHomeCare, 500, null, $errors);

        $exception = OutcomeUnknown::fromUnavailable($cause);

        static::assertSame(OutcomeUnknownReason::ServerError, $exception->reason());
        static::assertSame(500, $exception->httpStatus());
        static::assertSame(500, $exception->getCode());
        static::assertSame($errors, $exception->errors());
        static::assertStringContainsString('(server_error)', $exception->getMessage());
    }

    /**
     * @return iterable<string, array{UrssafUnavailable}>
     */
    public static function provideUnavailableThatDidNotReachUrssaf(): iterable
    {
        yield 'quota' => [UrssafUnavailable::withStatus(Operation::DeclareChildminder, 429, null, ApiErrors::none())];
        yield 'token failure' => [UrssafUnavailable::duringAuthentication(Operation::DeclareChildminder, 503, null, null)];
    }

    #[DataProvider('provideUnavailableThatDidNotReachUrssaf')]
    public function testOutcomeUnknownRefusesAFailureThatDidNotReachUrssaf(UrssafUnavailable $cause): void
    {
        try {
            $outcome = OutcomeUnknown::fromUnavailable($cause);
        } catch (InvariantViolation $violation) {
            static::assertSame('outcome_unknown.not_reached', $violation->violationCode());

            return;
        }

        static::fail('Unexpected outcome: ' . $outcome->getMessage());
    }

    public function testOutcomeUnknownAfterAnUnreadableResponse(): void
    {
        $cause = new class ('unreadable') extends RuntimeException implements UrssafException {};

        $exception = OutcomeUnknown::afterUnreadableResponse(Operation::DeclareChildminder, 200, $cause);

        static::assertSame(OutcomeUnknownReason::UnreadableResponse, $exception->reason());
        static::assertSame(200, $exception->httpStatus());
        static::assertSame(200, $exception->getCode());
        static::assertTrue($exception->errors()->isEmpty());
        static::assertSame($cause, $exception->getPrevious());
        static::assertStringContainsString('(unreadable_response)', $exception->getMessage());
    }

    public function testOutcomeUnknownReasonValues(): void
    {
        static::assertSame(
            ['transport', 'server_error', 'unreadable_response'],
            array_map(static fn(OutcomeUnknownReason $reason): string => $reason->value, OutcomeUnknownReason::cases()),
        );
    }
}
