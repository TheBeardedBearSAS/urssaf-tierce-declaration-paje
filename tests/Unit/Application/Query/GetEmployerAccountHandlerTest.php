<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Query;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetEmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetEmployerAccountHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\EmployerWorld;

final class GetEmployerAccountHandlerTest extends TestCase
{
    private EmployerWorld $world;

    private GetEmployerAccountHandler $handler;

    protected function setUp(): void
    {
        $this->world = new EmployerWorld();
        $this->handler = new GetEmployerAccountHandler($this->world->locator, $this->world->mandates);
    }

    public function testReadsAnAccountWithoutMandateNorSnapshot(): void
    {
        $this->world->trackedAccount();

        $view = ($this->handler)(new GetEmployerAccount(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('account-1', $view->accountId);
        static::assertSame('tracked', $view->stage);
        static::assertNull($view->mandate);
        static::assertNull($view->lastStatus);
        static::assertSame('2026-11-01T08:00:00Z', $view->createdAt);
        static::assertSame([], $this->world->gateway->calls());
    }

    public function testShowsTheActiveMandate(): void
    {
        $account = $this->world->trackedAccount();
        $this->world->registeredMandate($account);

        $view = ($this->handler)(new GetEmployerAccount(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('mandate-1', $view->mandate?->mandateId);
        static::assertSame('registered', $view->mandate->state);
    }

    public function testShowsTheMostRecentCancelledMandateWhenNoneIsActive(): void
    {
        $account = $this->world->trackedAccount();
        $first = $this->world->registeredMandate($account, 'mandate-1');
        $first->cancel(Instant::fromIso8601('2026-11-03T08:00:00Z'));
        $first->releaseEvents();
        $this->world->mandates->save($first);
        $second = $this->world->registeredMandate($account, 'mandate-2');
        $second->cancel(Instant::fromIso8601('2026-11-04T08:00:00Z'));
        $second->releaseEvents();
        $this->world->mandates->save($second);

        $view = ($this->handler)(new GetEmployerAccount(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('mandate-2', $view->mandate?->mandateId);
        static::assertSame('cancelled', $view->mandate->state);
    }

    public function testTheActiveMandateWinsOverAMoreRecentCancelledOne(): void
    {
        $account = $this->world->trackedAccount();
        $active = $this->world->registeredMandate($account, 'mandate-1');
        $cancelled = Mandate::register(
            MandateId::fromString('mandate-2'),
            $account->tenantId(),
            $account->externalReference(),
            $account->employer(),
            $account->id(),
            Instant::fromIso8601('2026-11-10T08:00:00Z'),
        );
        $cancelled->cancel(Instant::fromIso8601('2026-11-11T08:00:00Z'));
        $cancelled->releaseEvents();
        $this->world->mandates->save($cancelled);

        $view = ($this->handler)(new GetEmployerAccount(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame($active->id()->value(), $view->mandate?->mandateId);
    }

    public function testFindsTheAccountByEveryKindOfSelector(): void
    {
        $this->world->trackedAccount();

        foreach ([
            EmployerSelector::byAccountId(EmployerAccountId::fromString('account-1')),
            EmployerSelector::byEmployerNumber(PajeEmployerNumber::of(EmployerWorld::NUMBER)),
            EmployerWorld::selector(),
        ] as $selector) {
            static::assertSame('account-1', ($this->handler)(new GetEmployerAccount(EmployerWorld::tenant(), $selector))->accountId);
        }
    }

    public function testNeverShowsAnAccountOfAnotherTenant(): void
    {
        $this->world->trackedAccount();

        foreach ([
            EmployerSelector::byAccountId(EmployerAccountId::fromString('account-1')),
            EmployerSelector::byEmployerNumber(PajeEmployerNumber::of(EmployerWorld::NUMBER)),
            EmployerWorld::selector(),
        ] as $selector) {
            try {
                ($this->handler)(new GetEmployerAccount(EmployerWorld::tenant('tenant-2'), $selector));
                static::fail('Expected RecordNotFound.');
            } catch (RecordNotFound $notFound) {
                static::assertSame('employer_account.not_found', $notFound->violationCode());
                static::assertStringNotContainsString(EmployerWorld::NUMBER, $notFound->getMessage());
                static::assertStringNotContainsString(EmployerWorld::NUMBER, (string) json_encode($notFound->params()));
            }
        }
    }
}
