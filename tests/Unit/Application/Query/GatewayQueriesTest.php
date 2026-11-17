<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Query;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\EstimateContributions;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\EstimateContributionsHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\VerifyChildren;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\VerifyChildrenHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\VerifyEmployee;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\VerifyEmployeeHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildEntitlementStatus;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\PajeEmployeeNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\OnlineAccountState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\ContributionEstimate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message\NonBlockingMessage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\UpperCaseName;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\EmployerWorld;

final class GatewayQueriesTest extends TestCase
{
    private EmployerWorld $world;

    protected function setUp(): void
    {
        $this->world = new EmployerWorld();
    }

    public function testVerifiesAnEmployee(): void
    {
        $this->world->gateway->queue('021', EmployeeAccount::of('Y2926822480004', false, true, OnlineAccountState::of('Actif')));

        $view = (new VerifyEmployeeHandler($this->world->gateway))(
            new VerifyEmployee(EmployerWorld::tenant(), EmployeeByNumber::of(PajeEmployeeNumber::of('Y2926822480004'))),
        );

        static::assertSame('Y2926822480004', $view->employeeNumber);
        static::assertFalse($view->childminder);
        static::assertTrue($view->homeCare);
        static::assertSame('Actif', $view->onlineAccountState);
        static::assertStringNotContainsString('Y2926822480004', print_r($view, true));
        static::assertSame(
            ['employeeNumber' => '***', 'childminder' => false, 'homeCare' => true, 'onlineAccountState' => 'Actif'],
            $view->__debugInfo(),
        );
        static::assertSame([], $this->world->accounts->all());
    }

    public function testVerifiesChildrenInTheOrderOfTheAnswer(): void
    {
        $child = Child::of(CalendarDate::fromString('2020-05-04'), UpperCaseName::of('DUPONT', 'last_name'), UpperCaseName::of('JEAN', 'first_name'));
        $this->world->gateway->queue('022', [
            ChildCheck::of($child, ChildEntitlementStatus::of(1)),
            ChildCheck::of(null, ChildEntitlementStatus::of(0)),
            ChildCheck::of(null, ChildEntitlementStatus::of(7)),
        ]);

        $views = (new VerifyChildrenHandler($this->world->gateway))(
            new VerifyChildren(EmployerWorld::tenant(), EmployerWorld::employer(), [$child]),
        );

        static::assertCount(3, $views);
        static::assertSame(1, $views[0]->status);
        static::assertSame('Entitled', $views[0]->entitlement);
        static::assertSame('NotEntitled', $views[1]->entitlement);
        static::assertSame(7, $views[2]->status);
        static::assertNull($views[2]->entitlement);
    }

    public function testEstimatesAHomeCareEmployment(): void
    {
        $this->world->gateway->queue('041', ContributionEstimate::of(
            Amount::fromCents(12_345),
            null,
            Amount::fromDecimalString('7'),
            [NonBlockingMessage::of('ER_API_041_NB_0001', 'message', null)],
        ));

        $view = (new EstimateContributionsHandler($this->world->gateway))(
            new EstimateContributions(EmployerWorld::tenant(), EstimateFixtures::homeCare()),
        );

        static::assertSame('123.45', $view->coveredByFamilyBenefits);
        static::assertNull($view->employerShare);
        static::assertSame('7.00', $view->employerExemption);
        static::assertSame('ER_API_041_NB_0001', $view->messages[0]->code);
        static::assertNull($view->messages[0]->description);
        static::assertSame(['041'], $this->world->gateway->calls());
    }

    public function testEstimatesAChildminderEmployment(): void
    {
        $this->world->gateway->queue('040', ContributionEstimate::of(null, Amount::fromCents(100), null, []));

        $view = (new EstimateContributionsHandler($this->world->gateway))(
            new EstimateContributions(EmployerWorld::tenant(), EstimateFixtures::childminder()),
        );

        static::assertNull($view->coveredByFamilyBenefits);
        static::assertSame('1.00', $view->employerShare);
        static::assertSame([], $view->messages);
        static::assertSame(['040'], $this->world->gateway->calls());
    }

    public function testFailuresPropagate(): void
    {
        $this->world->gateway->queue('040', UrssafUnavailable::withStatus(Operation::EstimateChildminder, 503, null, ApiErrors::none()));

        $this->expectException(UrssafUnavailable::class);

        (new EstimateContributionsHandler($this->world->gateway))(new EstimateContributions(EmployerWorld::tenant(), EstimateFixtures::childminder()));
    }
}
