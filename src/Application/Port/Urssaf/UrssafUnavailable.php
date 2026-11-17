<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf;

use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RetryableException;
use Throwable;

/**
 * Transient failure: network error, timeout, HTTP 429 (quota, ADR 0011), 500 / 502 / 503 / 504. Retryable.
 *
 * {@see self::mayHaveReachedUrssaf()} tells whether URSSAF may have processed the call anyway: true for transport
 * errors (e.g. a timeout once the request was sent) and HTTP 5xx answers of the API call; false for HTTP 429 (refused
 * before processing) and for every failure of the token request (the API call itself was never sent).
 *
 * On a never-resent operation (050, 051, and 023, 030, 031 unless declared safe, ADR 0003) the gateway never throws it
 * when `mayHaveReachedUrssaf()` is true: it throws {@see OutcomeUnknown} instead.
 *
 * The message holds the operation number and the HTTP status only (ADR 0007).
 */
final class UrssafUnavailable extends RuntimeException implements RetryableException
{
    private function __construct(
        string $message,
        private readonly Operation $operation,
        private readonly ?int $httpStatus,
        private readonly ?int $retryAfterSeconds,
        private readonly bool $mayHaveReachedUrssaf,
        private readonly bool $whileAuthenticating,
        private readonly ApiErrors $errors,
        ?Throwable $previous,
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }

    /**
     * The API call answered a transient HTTP status (429, 5xx).
     *
     * @param int|null  $retryAfterSeconds value of the Retry-After header when provided
     * @param ApiErrors $errors            the parsed body of a technical-error answer, none otherwise
     */
    public static function withStatus(
        Operation $operation,
        int $httpStatus,
        ?int $retryAfterSeconds,
        ApiErrors $errors,
    ): self {
        return new self(
            \sprintf('URSSAF method %s is temporarily unavailable (HTTP %d).', $operation->value, $httpStatus),
            $operation,
            $httpStatus,
            $retryAfterSeconds,
            $httpStatus >= 500,
            false,
            $errors,
            null,
        );
    }

    /**
     * The API call failed without an HTTP answer (e.g. connection error, timeout). The previous exception must be
     * sanitised by the caller: it must not carry the URL (query string, ADR 0007).
     */
    public static function dueToTransportError(Operation $operation, Throwable $previous): self
    {
        return new self(
            \sprintf('URSSAF method %s could not be reached.', $operation->value),
            $operation,
            null,
            null,
            true,
            false,
            ApiErrors::none(),
            $previous,
        );
    }

    /**
     * The access token needed by the call could not be obtained (transient HTTP status, or transport error when
     * $httpStatus is null): the API call itself was not sent.
     */
    public static function duringAuthentication(
        Operation $operation,
        ?int $httpStatus,
        ?int $retryAfterSeconds,
        ?Throwable $previous,
    ): self {
        return new self(
            \sprintf('URSSAF authentication for method %s is temporarily unavailable.', $operation->value),
            $operation,
            $httpStatus,
            $retryAfterSeconds,
            false,
            true,
            ApiErrors::none(),
            $previous,
        );
    }

    public function operation(): Operation
    {
        return $this->operation;
    }

    /**
     * Null for transport errors (no HTTP response).
     */
    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function retryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }

    /**
     * The error list of a technical-error answer, or none.
     */
    public function errors(): ApiErrors
    {
        return $this->errors;
    }

    public function isQuotaExceeded(): bool
    {
        return $this->httpStatus === 429;
    }

    /**
     * True when URSSAF may have processed the call despite the failure (see the class documentation).
     */
    public function mayHaveReachedUrssaf(): bool
    {
        return $this->mayHaveReachedUrssaf;
    }

    /**
     * True when the failure happened while obtaining the access token (the API call was never sent).
     */
    public function whileAuthenticating(): bool
    {
        return $this->whileAuthenticating;
    }
}
