<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Exception;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\EmployerAlreadyTracked;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\EmployerErasureRefused;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;

final class EmployerExceptionsTest extends TestCase
{
    public function testAlreadyTrackedByExternalReference(): void
    {
        $exception = EmployerAlreadyTracked::withExternalReference(ExternalReference::fromString('employer-ref-1'), EmployerAccountId::fromString('account-1'));

        static::assertSame('employer_account.already_tracked', $exception->violationCode());
        static::assertSame(['external_reference' => 'employer-ref-1', 'employer_account_id' => 'account-1'], $exception->params());
        static::assertSame('The external reference is already used by another employer account.', $exception->getMessage());
    }

    public function testAlreadyTrackedByEmployerNumberCarriesNoNumber(): void
    {
        $exception = EmployerAlreadyTracked::withEmployerNumber(EmployerAccountId::fromString('account-1'));

        static::assertSame('employer_account.already_tracked', $exception->violationCode());
        static::assertSame(['employer_account_id' => 'account-1'], $exception->params());
        static::assertSame('The employer is already tracked by another employer account.', $exception->getMessage());
    }

    public function testErasureRefusals(): void
    {
        $id = EmployerAccountId::fromString('account-1');

        $active = EmployerErasureRefused::activeMandate($id);
        $inFlight = EmployerErasureRefused::submissionInFlight($id, 3);
        $unknown = EmployerErasureRefused::unknownOutcomes($id, 2);

        static::assertSame('employer_account.forget_active_mandate', $active->violationCode());
        static::assertSame(['employer_account_id' => 'account-1'], $active->params());
        static::assertSame('employer_account.forget_submission_in_flight', $inFlight->violationCode());
        static::assertSame(['employer_account_id' => 'account-1', 'count' => 3], $inFlight->params());
        static::assertStringContainsString('3 declaration(s)', $inFlight->getMessage());
        static::assertStringContainsString('being submitted', $inFlight->getMessage());
        static::assertSame('employer_account.forget_unknown_outcome', $unknown->violationCode());
        static::assertSame(['employer_account_id' => 'account-1', 'count' => 2], $unknown->params());
        static::assertStringContainsString('2 declaration(s)', $unknown->getMessage());
        static::assertStringContainsString('unknown outcome', $unknown->getMessage());
        static::assertStringContainsString('method 031', $active->getMessage());
    }
}
