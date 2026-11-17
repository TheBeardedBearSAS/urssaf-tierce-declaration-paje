<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\AssociateEmployee;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\AssociateEmployeeHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\ActivityType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\AdministrativeIdentity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeAssociation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByAdministrativeData;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeCreation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\PajeEmployeeNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\OnlineAccountState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message\NonBlockingMessage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\UpperCaseName;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\EmployerWorld;

final class AssociateEmployeeHandlerTest extends TestCase
{
    private EmployerWorld $world;

    private AssociateEmployeeHandler $handler;

    protected function setUp(): void
    {
        $this->world = new EmployerWorld();
        $this->handler = new AssociateEmployeeHandler($this->world->gateway);
    }

    public function testAssociatesAnEmployeeIdentifiedByNumber(): void
    {
        $this->world->gateway->queue('023', EmployeeAssociation::of(
            true,
            true,
            false,
            EmployeeAccount::of('Y2926822480004', true, false, OnlineAccountState::of('Actif')),
            [NonBlockingMessage::of('ER_API_023_NB_0001', 'message', 'description')],
        ));

        $view = ($this->handler)(new AssociateEmployee(
            EmployerWorld::tenant(),
            EmployerWorld::employer(),
            EmployeeByNumber::of(PajeEmployeeNumber::of('Y2926822480004')),
            EmployeeCreation::of(null, null, null, null, null, null),
        ));

        static::assertTrue($view->created);
        static::assertTrue($view->associated);
        static::assertFalse($view->alreadyAssociated);
        static::assertSame('Y2926822480004', $view->account?->employeeNumber);
        static::assertTrue($view->account->childminder);
        static::assertFalse($view->account->homeCare);
        static::assertSame('Actif', $view->account->onlineAccountState);
        static::assertCount(1, $view->messages);
        static::assertSame('ER_API_023_NB_0001', $view->messages[0]->code);
        static::assertSame('message', $view->messages[0]->message);
        static::assertSame('description', $view->messages[0]->description);
        static::assertSame(['023'], $this->world->gateway->calls());
    }

    public function testAnAlreadyAssociatedEmployeeHasNoAccountNecessarily(): void
    {
        $this->world->gateway->queue('023', EmployeeAssociation::of(false, false, true, null, []));

        $view = ($this->handler)($this->command(self::byAdministrativeData(), self::creationWithIdentity()));

        static::assertTrue($view->alreadyAssociated);
        static::assertNull($view->account);
        static::assertSame([], $view->messages);
    }

    public function testAdministrativeDataNeedsTheIdentityOfTheCreation(): void
    {
        foreach ([
            EmployeeCreation::of(null, null, null, null, null, null),
            EmployeeCreation::of(ActivityType::homeCare(), CalendarDate::fromString('1980-01-30'), null, null, null, null),
            EmployeeCreation::of(null, null, null, null, null, AdministrativeIdentity::of(null, null, null, null, null, null, null, null)),
        ] as $creation) {
            try {
                ($this->handler)($this->command(self::byAdministrativeData(), $creation));
                static::fail('Expected ValidationFailed.');
            } catch (ValidationFailed $failed) {
                $errors = $failed->result()->errors();
                static::assertCount(1, $errors);
                static::assertSame('employee_association.identity_required', $errors[0]->code());
                static::assertSame('creation.identity', $errors[0]->path());
            }
        }

        static::assertSame([], $this->world->gateway->calls());
        static::assertSame([], $this->world->journal->entries());
    }

    public function testTheIdentityOfTheCreationIsNotRequiredForOtherIdentifications(): void
    {
        $this->world->gateway->queue('023', EmployeeAssociation::of(false, true, false, null, []));

        $view = ($this->handler)($this->command(
            EmployeeByNumber::of(PajeEmployeeNumber::of('Y2926822480004')),
            EmployeeCreation::of(null, null, null, null, null, null),
        ));

        static::assertTrue($view->associated);
    }

    public function testAnUnknownOutcomePropagates(): void
    {
        $unknown = OutcomeUnknown::fromUnavailable(UrssafUnavailable::withStatus(Operation::AssociateEmployee, 502, null, ApiErrors::none()));
        $this->world->gateway->queue('023', $unknown);

        $this->expectExceptionObject($unknown);

        ($this->handler)($this->command(self::byAdministrativeData(), self::creationWithIdentity()));
    }

    private function command(EmployeeByAdministrativeData|EmployeeByNumber $employee, EmployeeCreation $creation): AssociateEmployee
    {
        return new AssociateEmployee(EmployerWorld::tenant(), EmployerWorld::employer(), $employee, $creation);
    }

    private static function administrativeIdentity(): AdministrativeIdentity
    {
        return AdministrativeIdentity::of(null, UpperCaseName::of('DUPONT', 'birth_name'), null, null, null, null, null, null);
    }

    private static function byAdministrativeData(): EmployeeByAdministrativeData
    {
        return EmployeeByAdministrativeData::of(self::administrativeIdentity());
    }

    private static function creationWithIdentity(): EmployeeCreation
    {
        return EmployeeCreation::of(null, null, null, null, null, self::administrativeIdentity());
    }
}
