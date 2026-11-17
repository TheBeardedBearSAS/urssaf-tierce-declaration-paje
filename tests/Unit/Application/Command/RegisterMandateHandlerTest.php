<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RegisterMandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RegisterMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateOrigin;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\EmployerWorld;

final class RegisterMandateHandlerTest extends TestCase
{
    private EmployerWorld $world;

    private RegisterMandateHandler $handler;

    protected function setUp(): void
    {
        $this->world = new EmployerWorld();
        $this->handler = new RegisterMandateHandler(
            $this->world->locator,
            $this->world->mandates,
            $this->world->gateway,
            $this->world->recorder,
        );
    }

    public function testRegistersTheMandateAfterTheCall(): void
    {
        $this->world->trackedAccount();
        $this->world->gateway->queue('030', null);

        $view = ($this->handler)(new RegisterMandate(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('id-1', $view->mandateId);
        static::assertSame('tenant-1', $view->tenantId);
        static::assertSame('employer-ref-1', $view->externalReference);
        static::assertSame('account-1', $view->employerAccountId);
        static::assertSame(MandateOrigin::Registered->value, $view->origin);
        static::assertSame(MandateState::Registered->value, $view->state);
        static::assertSame('2026-11-17T09:00:00Z', $view->registeredAt);
        static::assertNull($view->cancelledAt);
        static::assertSame(['030'], $this->world->gateway->calls());
        static::assertSame(
            EmployerStage::MandateRegistered,
            $this->world->accounts->get(EmployerAccountId::fromString('account-1'))->stage(),
        );
        static::assertNotNull($this->world->mandates->findActive(EmployerWorld::tenant(), PajeEmployerNumber::of(EmployerWorld::NUMBER)));
        static::assertSame(
            [
                'call:030',
                'begin',
                'append:urssaf.paje.mandate.registered,urssaf.paje.employer.status_changed',
                'commit',
                'publish:MandateRegistered',
                'publish:EmployerStageChanged',
            ],
            $this->world->journal->entries(),
        );
    }

    public function testFindsTheEmployerByNumberOrAccountId(): void
    {
        $this->world->trackedAccount();
        $this->world->gateway->queue('030', null);

        $view = ($this->handler)(new RegisterMandate(
            EmployerWorld::tenant(),
            EmployerSelector::byEmployerNumber(PajeEmployerNumber::of(EmployerWorld::NUMBER)),
        ));

        static::assertSame('account-1', $view->employerAccountId);

        $this->world->accounts->save($this->world->accounts->get(EmployerAccountId::fromString('account-1')));
        static::assertSame(
            'id-1',
            ($this->handler)(new RegisterMandate(EmployerWorld::tenant(), EmployerSelector::byAccountId(EmployerAccountId::fromString('account-1'))))->mandateId,
        );
    }

    public function testReturnsTheActiveMandateWithoutAnyCall(): void
    {
        $account = $this->world->trackedAccount();
        $this->world->registeredMandate($account);

        $view = ($this->handler)(new RegisterMandate(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('mandate-1', $view->mandateId);
        static::assertSame([], $this->world->gateway->calls());
        static::assertSame([], $this->world->journal->entries());
    }

    public function testRegistersANewMandateAfterACancellation(): void
    {
        $account = $this->world->trackedAccount();
        $mandate = $this->world->registeredMandate($account);
        $mandate->cancel(Instant::fromIso8601('2026-11-10T00:00:00Z'));
        $mandate->releaseEvents();
        $this->world->mandates->save($mandate);
        $this->world->gateway->queue('030', null);

        $view = ($this->handler)(new RegisterMandate(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('id-1', $view->mandateId);
        static::assertSame(['030'], $this->world->gateway->calls());
        static::assertCount(2, $this->world->mandates->findByEmployer(EmployerWorld::tenant(), PajeEmployerNumber::of(EmployerWorld::NUMBER)));
    }

    public function testRecordsNothingWhenTheMandateIsHeldElsewhere(): void
    {
        $this->world->trackedAccount();
        $this->world->gateway->queue(
            '030',
            UrssafRequestRejected::withErrors(
                Operation::RegisterMandate,
                400,
                ApiErrors::of(ApiError::of('COMPTE_DEJA_MANDATE', null, null)),
                RejectionKind::MandateHeldElsewhere,
            ),
        );

        try {
            ($this->handler)(new RegisterMandate(EmployerWorld::tenant(), EmployerWorld::selector()));
            static::fail('Expected UrssafRequestRejected.');
        } catch (UrssafRequestRejected $rejection) {
            static::assertTrue($rejection->is(RejectionKind::MandateHeldElsewhere));
        }

        $this->assertNothingRecorded();
    }

    public function testRecordsNothingAndRethrowsAnUnknownOutcome(): void
    {
        $this->world->trackedAccount();
        $unknown = OutcomeUnknown::fromUnavailable(UrssafUnavailable::withStatus(Operation::RegisterMandate, 500, null, ApiErrors::none()));
        $this->world->gateway->queue('030', $unknown);

        try {
            ($this->handler)(new RegisterMandate(EmployerWorld::tenant(), EmployerWorld::selector()));
            static::fail('Expected OutcomeUnknown.');
        } catch (OutcomeUnknown $caught) {
            static::assertSame($unknown, $caught);
        }

        $this->assertNothingRecorded();
    }

    public function testRecordsNothingOnATemporaryFailure(): void
    {
        $this->world->trackedAccount();
        $this->world->gateway->queue('030', UrssafUnavailable::duringAuthentication(Operation::RegisterMandate, 503, null, null));

        $this->expectException(UrssafUnavailable::class);

        try {
            ($this->handler)(new RegisterMandate(EmployerWorld::tenant(), EmployerWorld::selector()));
        } finally {
            $this->assertNothingRecorded();
        }
    }

    public function testRefusesAnUntrackedEmployerWithoutAnyCall(): void
    {
        $this->expectException(RecordNotFound::class);

        try {
            ($this->handler)(new RegisterMandate(EmployerWorld::tenant(), EmployerWorld::selector()));
        } finally {
            static::assertSame([], $this->world->gateway->calls());
        }
    }

    private function assertNothingRecorded(): void
    {
        static::assertSame([], $this->world->mandates->all());
        static::assertSame(EmployerStage::Tracked, $this->world->accounts->get(EmployerAccountId::fromString('account-1'))->stage());
        static::assertSame(['call:030'], $this->world->journal->entries());
    }
}
