<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\TrackEmployer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\TrackEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\EmployerAlreadyTracked;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerOrigin;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\EmployerWorld;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employer\EmployerSamples;

/**
 * @mago-expect lint:too-many-methods
 */
final class TrackEmployerHandlerTest extends TestCase
{
    private EmployerWorld $world;

    private TrackEmployerHandler $handler;

    protected function setUp(): void
    {
        $this->world = new EmployerWorld();
        $this->handler = new TrackEmployerHandler(
            $this->world->locator,
            $this->world->accounts,
            $this->world->mandates,
            $this->world->gateway,
            $this->world->ids,
            $this->world->clock,
            $this->world->unitOfWork,
        );
    }

    public function testTracksTheEmployerWithItsFirstSnapshot(): void
    {
        $this->world->gateway->queue('020', EmployerSamples::verification(true, 'Actif'));

        $view = ($this->handler)(new TrackEmployer(EmployerWorld::tenant(), EmployerWorld::reference(), EmployerWorld::employer()));

        static::assertSame('id-1', $view->accountId);
        static::assertSame('tenant-1', $view->tenantId);
        static::assertSame('employer-ref-1', $view->externalReference);
        static::assertSame(EmployerWorld::NUMBER, $view->employerNumber);
        static::assertSame(EmployerOrigin::Tracked->value, $view->origin);
        static::assertSame(EmployerStage::Tracked->value, $view->stage);
        static::assertNotNull($view->lastStatus);
        static::assertTrue($view->lastStatus->authorised);
        static::assertSame('Actif', $view->lastStatus->onlineAccountState);
        static::assertSame('2026-11-17T09:00:00Z', $view->lastStatus->checkedAt);
        static::assertSame('2026-11-17T09:00:00Z', $view->createdAt);
        static::assertSame('2026-11-17T09:00:00Z', $view->updatedAt);
        static::assertNull($view->mandate);

        $stored = $this->world->accounts->get(EmployerAccountId::fromString('id-1'));
        static::assertSame('Actif', $stored->lastVerification()?->state()->raw());
        static::assertSame(['020'], $this->world->gateway->calls());
        static::assertSame(['EmployerRegistered', 'EmployerStatusRefreshed'], $this->world->publishedEvents());
    }

    public function testCallsUrssafBeforeTheTransaction(): void
    {
        $this->world->gateway->queue('020', EmployerSamples::verification());

        ($this->handler)(new TrackEmployer(EmployerWorld::tenant(), EmployerWorld::reference(), EmployerWorld::employer()));

        static::assertSame(
            [
                'call:020',
                'begin',
                'append:urssaf.paje.employer.status_changed',
                'commit',
                'publish:EmployerRegistered',
                'publish:EmployerStatusRefreshed',
            ],
            $this->world->journal->entries(),
        );
    }

    public function testReturnsTheTrackedAccountForTheSameEmployerWithoutAnyCall(): void
    {
        $account = $this->world->trackedAccount();
        $this->world->registeredMandate($account);

        $view = ($this->handler)(new TrackEmployer(EmployerWorld::tenant(), EmployerWorld::reference(), EmployerWorld::employer()));

        static::assertSame('account-1', $view->accountId);
        static::assertSame('mandate-1', $view->mandate?->mandateId);
        static::assertSame([], $this->world->gateway->calls());
        static::assertSame([], $this->world->journal->entries());
    }

    public function testReplayWithoutMandateHasNone(): void
    {
        $this->world->trackedAccount();

        $view = ($this->handler)(new TrackEmployer(EmployerWorld::tenant(), EmployerWorld::reference(), EmployerWorld::employer()));

        static::assertNull($view->mandate);
    }

    public function testRefusesTheExternalReferenceOfAnotherEmployer(): void
    {
        $this->world->trackedAccount();

        $this->expectException(EmployerAlreadyTracked::class);

        try {
            ($this->handler)(new TrackEmployer(EmployerWorld::tenant(), EmployerWorld::reference(), EmployerWorld::employer(EmployerWorld::OTHER_NUMBER)));
        } finally {
            static::assertSame([], $this->world->gateway->calls());
        }
    }

    public function testRefusesTheExternalReferenceOfTheSameNumberWithAnotherBirthDate(): void
    {
        $this->world->trackedAccount();

        $this->expectException(EmployerAlreadyTracked::class);

        ($this->handler)(new TrackEmployer(EmployerWorld::tenant(), EmployerWorld::reference(), EmployerWorld::employer(EmployerWorld::NUMBER, '1980-01-01')));
    }

    public function testRefusesAnEmployerTrackedUnderAnotherReference(): void
    {
        $this->world->trackedAccount();

        try {
            ($this->handler)(new TrackEmployer(EmployerWorld::tenant(), EmployerWorld::reference('employer-ref-2'), EmployerWorld::employer()));
            static::fail('Expected EmployerAlreadyTracked.');
        } catch (EmployerAlreadyTracked $refused) {
            static::assertSame('employer_account.already_tracked', $refused->violationCode());
            static::assertSame(['employer_account_id' => 'account-1'], $refused->params());
            static::assertSame([], $this->world->gateway->calls());
        }
    }

    public function testTheSameEmployerMayBeTrackedByAnotherTenant(): void
    {
        $this->world->trackedAccount();
        $this->world->gateway->queue('020', EmployerSamples::verification());

        $view = ($this->handler)(new TrackEmployer(EmployerWorld::tenant('tenant-2'), EmployerWorld::reference(), EmployerWorld::employer()));

        static::assertSame('tenant-2', $view->tenantId);
        static::assertSame(['020'], $this->world->gateway->calls());
    }

    public function testStoresNothingWhenUrssafRejectsTheEmployer(): void
    {
        $this->world->gateway->queue(
            '020',
            UrssafRequestRejected::withErrors(
                Operation::VerifyEmployer,
                400,
                ApiErrors::of(ApiError::of('EMPLOYEUR_INCONNU', null, null)),
                RejectionKind::EmployerNotFound,
            ),
        );

        try {
            ($this->handler)(new TrackEmployer(EmployerWorld::tenant(), EmployerWorld::reference(), EmployerWorld::employer()));
            static::fail('Expected UrssafRequestRejected.');
        } catch (UrssafRequestRejected $rejection) {
            static::assertSame(RejectionKind::EmployerNotFound, $rejection->kind());
        }

        static::assertSame([], $this->world->accounts->all());
        static::assertSame(['call:020'], $this->world->journal->entries());
    }

    public function testStoresNothingOnATemporaryFailure(): void
    {
        $this->world->gateway->queue('020', UrssafUnavailable::withStatus(Operation::VerifyEmployer, 503, null, ApiErrors::none()));

        $this->expectException(UrssafUnavailable::class);

        try {
            ($this->handler)(new TrackEmployer(EmployerWorld::tenant(), EmployerWorld::reference(), EmployerWorld::employer()));
        } finally {
            static::assertSame([], $this->world->accounts->all());
        }
    }
}
