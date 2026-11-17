<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\DeclarationAlreadyRecorded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\MandateRequired;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RetryableException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;

final class DeclarationExceptionsTest extends TestCase
{
    public function testAConflictingReferenceNamesTheRecordsOnly(): void
    {
        $conflict = DeclarationAlreadyRecorded::withExternalReference(ExternalReference::fromString('ref-1'), DeclarationRecordId::fromString('record-1'));

        static::assertSame('declaration_record.already_recorded', $conflict->violationCode());
        static::assertSame(['external_reference' => 'ref-1', 'declaration_record_id' => 'record-1'], $conflict->params());
        static::assertSame('The external reference is already recorded with another declaration content or employer.', $conflict->getMessage());
    }

    public function testAMissingMandateNamesTheOperationOnly(): void
    {
        $required = MandateRequired::forOperation(Operation::DeclareChildminder);

        static::assertSame('mandate.required', $required->violationCode());
        static::assertSame(['operation' => '050'], $required->params());
        static::assertSame('No active local mandate is known for the employer: method 050 is refused locally (ADR 0009).', $required->getMessage());
    }

    public function testARetryableFailureCarriesTheDelayOfItsCause(): void
    {
        $unavailable = UrssafUnavailable::withStatus(Operation::DeclareChildminder, 429, 30, ApiErrors::none());
        $limited = RateLimitExceeded::forOperation(Operation::DeclareHomeCare, 7);

        $first = RetryableUrssafFailure::causedBy($unavailable);
        $second = RetryableUrssafFailure::causedBy($limited);

        static::assertSame($unavailable, $first->getPrevious());
        static::assertSame(30, $first->retryAfterSeconds());
        static::assertSame(0, $first->getCode());
        static::assertSame(7, $second->retryAfterSeconds());
        static::assertSame('URSSAF is temporarily unavailable: the declaration must be retried later.', $first->getMessage());
    }

    public function testARetryableFailureWithoutAKnownDelayHasNone(): void
    {
        $other = new class ('later') extends RuntimeException implements RetryableException {};

        static::assertNull(RetryableUrssafFailure::causedBy($other)->retryAfterSeconds());
    }
}
