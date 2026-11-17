<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Testing;

use LogicException;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildEntitlement;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\ActivityType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeAssociation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeCreation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeIdentification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeIdentificationKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\OnlineAccountStateValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Testing\SimulatedPajeGateway;

/**
 * @mago-expect lint:too-many-methods
 */
final class SimulatedPajeGatewayEmployerTest extends TestCase
{
    public function testAKnownEmployerIsVerifiedWithItsMandateState(): void
    {
        $gateway = SimulatorSamples::gateway();

        $unmandated = $gateway->verifyEmployer(SimulatorSamples::tenant(), SimulatorSamples::employer());
        $gateway->mandate(SimulatorSamples::employerNumber(), SimulatorSamples::tenant());
        $mandated = $gateway->verifyEmployer(SimulatorSamples::tenant(), SimulatorSamples::employer());
        $other = $gateway->verifyEmployer(SimulatorSamples::otherTenant(), SimulatorSamples::employer());

        static::assertFalse($unmandated->authorised());
        static::assertSame('Actif', $unmandated->state()->raw());
        static::assertSame(OnlineAccountStateValue::Active, $unmandated->state()->documented());
        static::assertTrue($mandated->authorised());
        static::assertFalse($other->authorised());
        static::assertTrue($gateway->isMandated(SimulatorSamples::employerNumber(), SimulatorSamples::tenant()));
        static::assertFalse($gateway->isMandated(SimulatorSamples::employerNumber(), SimulatorSamples::otherTenant()));
        static::assertFalse($gateway->isMandated(SimulatorSamples::employerNumber('Y9999999999999'), SimulatorSamples::tenant()));
    }

    public function testTheOnlineAccountStateAndTheMandateCanBeScripted(): void
    {
        $gateway = SimulatorSamples::gateway();
        $gateway->mandate(SimulatorSamples::employerNumber(), SimulatorSamples::tenant());
        $gateway->setOnlineAccountState(SimulatorSamples::employerNumber(), 'Inactif');

        $inactive = $gateway->verifyEmployer(SimulatorSamples::tenant(), SimulatorSamples::employer());
        $gateway->withdrawMandate(SimulatorSamples::employerNumber());
        $withdrawn = $gateway->verifyEmployer(SimulatorSamples::tenant(), SimulatorSamples::employer());

        static::assertSame('Inactif', $inactive->state()->raw());
        static::assertTrue($inactive->authorised());
        static::assertFalse($withdrawn->authorised());
    }

    public function testAnUnknownEmployerIsRefusedLikeTheRealApi(): void
    {
        $gateway = SimulatorSamples::gateway();

        $rejection = SimulatorSamples::rejection(static fn(): EmployerVerification => $gateway->verifyEmployer(SimulatorSamples::tenant(), SimulatorSamples::employer(SimulatorSamples::OTHER_EMPLOYER_NUMBER)));

        static::assertSame(Operation::VerifyEmployer, $rejection->operation());
        static::assertSame(400, $rejection->httpStatus());
        static::assertSame(RejectionKind::EmployerNotFound, $rejection->kind());
        static::assertTrue($rejection->hasErrorCode('ER_API_020_0010'));
        static::assertSame('Aucun employeur trouvé pour ce numéro', $rejection->errors()->first()?->message());
        static::assertSame([Operation::VerifyEmployer], $gateway->calls());
    }

    public function testABirthDateThatDiffersIsRefused(): void
    {
        $gateway = SimulatorSamples::gateway();

        $rejection = SimulatorSamples::rejection(static fn(): EmployerVerification => $gateway->verifyEmployer(SimulatorSamples::tenant(), SimulatorSamples::employer(SimulatorSamples::EMPLOYER_NUMBER, '1976-02-23')));

        static::assertSame(RejectionKind::BirthDateMismatch, $rejection->kind());
        static::assertTrue($rejection->hasErrorCode('ER_API_020_0000'));
        static::assertSame('La date de naissance et le numéro Pajemploi ne correspondent pas', $rejection->errors()->first()?->message());
    }

    public function testAnEmployerIsKnownOnce(): void
    {
        $gateway = SimulatorSamples::gateway();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The employer is already known to the simulator.');

        $gateway->knowEmployer(SimulatorSamples::employer());
    }

    public function testScriptingAnUnknownEmployerFailsWithoutItsNumber(): void
    {
        $gateway = new SimulatedPajeGateway();

        try {
            $gateway->mandate(SimulatorSamples::employerNumber(), SimulatorSamples::tenant());
            static::fail('Expected a LogicException.');
        } catch (LogicException $logic) {
            static::assertSame('The employer is not known to the simulator.', $logic->getMessage());
            static::assertStringNotContainsString(SimulatorSamples::EMPLOYER_NUMBER, $logic->getMessage());
        }
    }

    public function testAnEmployeeIsFoundByItsNumberOrByItsAliases(): void
    {
        $gateway = new SimulatedPajeGateway();
        $gateway->knowEmployee(
            SimulatorSamples::employeeNumber(),
            true,
            false,
            SimulatorSamples::byNir(),
            SimulatorSamples::byIdentity('DUPONT', 'MARTIN', 'MARIE', '1980-01-30', 'PARIS', '100'),
        );

        $byNumber = $gateway->verifyEmployee(SimulatorSamples::tenant(), SimulatorSamples::byNumber());
        $byNir = $gateway->verifyEmployee(SimulatorSamples::tenant(), SimulatorSamples::byNir());
        $byIdentity = $gateway->verifyEmployee(SimulatorSamples::tenant(), SimulatorSamples::byIdentity('DUPONT', 'MARTIN', 'MARIE', '1980-01-30', 'PARIS', '100'));

        foreach ([$byNumber, $byNir, $byIdentity] as $account) {
            static::assertSame(SimulatorSamples::EMPLOYEE_NUMBER, $account->employeeNumber());
            static::assertTrue($account->childminder());
            static::assertFalse($account->homeCare());
            static::assertSame('Actif', $account->state()->raw());
        }
        static::assertSame([Operation::VerifyEmployee, Operation::VerifyEmployee, Operation::VerifyEmployee], $gateway->calls());
    }

    public function testAnotherEmployeeIsAbsent(): void
    {
        $gateway = new SimulatedPajeGateway();
        $gateway->knowEmployee(
            SimulatorSamples::employeeNumber(),
            false,
            true,
            SimulatorSamples::byNir(),
            SimulatorSamples::identity(),
        );

        $others = [
            SimulatorSamples::byNumber('Z9999999999999'),
            SimulatorSamples::byNir('275037512345679'),
            SimulatorSamples::byIdentity('DURAND', null, 'MARIE', '1980-01-30', 'PARIS', '100'),
            SimulatorSamples::byIdentity('DUPONT', 'MARTIN', 'MARIE', '1980-01-30', 'PARIS', '100'),
            SimulatorSamples::byIdentity('DUPONT', null, 'LUCIE', '1980-01-30', 'PARIS', '100'),
            SimulatorSamples::byIdentity('DUPONT', null, 'MARIE', '1980-01-31', 'PARIS', '100'),
            SimulatorSamples::byIdentity('DUPONT', null, 'MARIE', '1980-01-30', 'LYON', '100'),
            SimulatorSamples::byIdentity('DUPONT', null, 'MARIE', '1980-01-30', 'PARIS', '101'),
            new class implements EmployeeIdentification {
                public function kind(): EmployeeIdentificationKind
                {
                    return EmployeeIdentificationKind::Number;
                }

                public function birthDate(): ?CalendarDate
                {
                    return null;
                }
            },
        ];

        foreach ($others as $other) {
            $account = $gateway->verifyEmployee(SimulatorSamples::tenant(), $other);

            static::assertNull($account->employeeNumber());
            static::assertFalse($account->childminder());
            static::assertFalse($account->homeCare());
            static::assertSame('Absent', $account->state()->raw());
        }
    }

    public function testAnEmployeeIsFoundByPartialAdministrativeData(): void
    {
        $gateway = new SimulatedPajeGateway();
        $gateway->knowEmployee(
            SimulatorSamples::employeeNumber(),
            true,
            false,
            SimulatorSamples::partialIdentity('DUPONT', null, null),
            SimulatorSamples::partialIdentity(null, 'MARIE', null),
            SimulatorSamples::partialIdentity(null, null, '1980-01-30'),
        );
        $tenant = SimulatorSamples::tenant();

        foreach ([['DUPONT', null, null], [null, 'MARIE', null], [null, null, '1980-01-30']] as [$birthName, $firstName, $birthDate]) {
            $found = $gateway->verifyEmployee($tenant, SimulatorSamples::partialIdentity($birthName, $firstName, $birthDate));
            static::assertSame(SimulatorSamples::EMPLOYEE_NUMBER, $found->employeeNumber());
        }
        foreach ([['DURAND', null, null], [null, 'LUCIE', null], [null, null, '1980-01-31']] as [$birthName, $firstName, $birthDate]) {
            $absent = $gateway->verifyEmployee($tenant, SimulatorSamples::partialIdentity($birthName, $firstName, $birthDate));
            static::assertNull($absent->employeeNumber());
        }
    }

    public function testChildrenAreEntitledWhenKnownToTheEmployer(): void
    {
        $gateway = SimulatorSamples::gateway();
        $gateway->knowEmployer(SimulatorSamples::employer(SimulatorSamples::OTHER_EMPLOYER_NUMBER));
        $gateway->entitleChild(SimulatorSamples::employerNumber(), SimulatorSamples::child('JEAN', 'DUPONT', '2015-01-12'));

        $checks = $gateway->verifyChildren(SimulatorSamples::tenant(), SimulatorSamples::employer(), [
            SimulatorSamples::child('JEAN', 'DUPONT', '2015-01-12'),
            SimulatorSamples::child('LUCIE', 'DUPONT', '2015-01-12'),
            SimulatorSamples::child('JEAN', 'MARTIN', '2015-01-12'),
            SimulatorSamples::child('JEAN', 'DUPONT', '2015-01-13'),
        ]);
        $other = $gateway->verifyChildren(SimulatorSamples::tenant(), SimulatorSamples::employer(SimulatorSamples::OTHER_EMPLOYER_NUMBER), [SimulatorSamples::child('JEAN', 'DUPONT', '2015-01-12')]);

        static::assertCount(4, $checks);
        static::assertSame(ChildEntitlement::Entitled, $checks[0]->status()->documented());
        static::assertSame(SimulatorSamples::child('JEAN', 'DUPONT', '2015-01-12')->firstName()->value(), $checks[0]->child()?->firstName()->value());
        static::assertSame(ChildEntitlement::NotEntitled, $checks[1]->status()->documented());
        static::assertSame(ChildEntitlement::NotEntitled, $checks[2]->status()->documented());
        static::assertSame(ChildEntitlement::NotEntitled, $checks[3]->status()->documented());
        static::assertSame(ChildEntitlement::NotEntitled, $other[0]->status()->documented());
    }

    public function testChildrenOfAnUnknownEmployerOrWithAnotherBirthDateAreRefused(): void
    {
        $gateway = SimulatorSamples::gateway();

        $unknown = SimulatorSamples::rejection(static fn(): array => $gateway->verifyChildren(SimulatorSamples::tenant(), SimulatorSamples::employer(SimulatorSamples::OTHER_EMPLOYER_NUMBER), []));
        $mismatch = SimulatorSamples::rejection(static fn(): array => $gateway->verifyChildren(SimulatorSamples::tenant(), SimulatorSamples::employer(SimulatorSamples::EMPLOYER_NUMBER, '1976-02-23'), []));

        static::assertSame(Operation::VerifyChildren, $unknown->operation());
        static::assertTrue($unknown->hasErrorCode('ER_API_020_0010'));
        static::assertTrue($mismatch->hasErrorCode('ER_API_020_0000'));
    }

    public function testAKnownEmployeeIsAssociatedOnce(): void
    {
        $gateway = SimulatorSamples::gateway();
        $gateway->knowEmployee(SimulatorSamples::employeeNumber(), false, true);

        $first = $gateway->associateEmployee(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::byNumber(), EmployeeCreation::of(null, null, null, null, null, null));
        $second = $gateway->associateEmployee(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::byNumber(), EmployeeCreation::of(null, null, null, null, null, null));

        static::assertFalse($first->created());
        static::assertTrue($first->associated());
        static::assertFalse($first->alreadyAssociated());
        static::assertSame([], $first->messages());
        static::assertSame(SimulatorSamples::EMPLOYEE_NUMBER, $first->account()?->employeeNumber());
        static::assertFalse($second->created());
        static::assertFalse($second->associated());
        static::assertTrue($second->alreadyAssociated());
        static::assertCount(1, $second->messages());
        static::assertSame('ER_API_023_NB_0000', $second->messages()[0]->code());
        static::assertSame(SimulatorSamples::EMPLOYEE_NUMBER, $second->account()?->employeeNumber());
    }

    public function testAnAssociationIsPerEmployer(): void
    {
        $gateway = SimulatorSamples::gateway();
        $gateway->knowEmployer(SimulatorSamples::employer(SimulatorSamples::OTHER_EMPLOYER_NUMBER));
        $gateway->knowEmployee(SimulatorSamples::employeeNumber(), false, true);
        $empty = EmployeeCreation::of(null, null, null, null, null, null);
        $gateway->associateEmployee(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::byNumber(), $empty);

        $other = $gateway->associateEmployee(SimulatorSamples::tenant(), SimulatorSamples::employer(SimulatorSamples::OTHER_EMPLOYER_NUMBER), SimulatorSamples::byNumber(), $empty);

        static::assertTrue($other->associated());
        static::assertFalse($other->alreadyAssociated());
    }

    public function testAnUnknownEmployeeIsCreatedWhenCreationDataIsGiven(): void
    {
        $gateway = SimulatorSamples::gateway();
        $homeCare = EmployeeCreation::of(ActivityType::homeCare(), CalendarDate::fromString('1980-01-30'), null, null, null, null);
        $childminder = EmployeeCreation::of(ActivityType::childminder(), CalendarDate::fromString('1981-01-30'), null, null, null, null);

        $first = $gateway->associateEmployee(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::byNir(), $homeCare);
        $second = $gateway->associateEmployee(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::byNir('275037512345679'), $childminder);
        $found = $gateway->verifyEmployee(SimulatorSamples::tenant(), SimulatorSamples::byNir());

        static::assertTrue($first->created());
        static::assertTrue($first->associated());
        static::assertFalse($first->alreadyAssociated());
        $firstAccount = $first->account();
        $secondAccount = $second->account();
        static::assertNotNull($firstAccount);
        static::assertNotNull($secondAccount);
        static::assertSame('SIMSAL00000001', $firstAccount->employeeNumber());
        static::assertFalse($firstAccount->childminder());
        static::assertTrue($firstAccount->homeCare());
        static::assertSame('SIMSAL00000002', $secondAccount->employeeNumber());
        static::assertTrue($secondAccount->childminder());
        static::assertFalse($secondAccount->homeCare());
        static::assertSame('SIMSAL00000001', $found->employeeNumber());
    }

    public function testACreatedEmployeeIsAssociatedAndNotCreatedAgain(): void
    {
        $gateway = SimulatorSamples::gateway();
        $creation = EmployeeCreation::of(null, CalendarDate::fromString('1980-01-30'), null, null, null, null);

        $created = $gateway->associateEmployee(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::byNir(), $creation);
        $again = $gateway->associateEmployee(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::byNir(), $creation);

        $account = $created->account();
        static::assertNotNull($account);
        static::assertTrue($created->created());
        static::assertTrue($account->childminder());
        static::assertFalse($account->homeCare());
        static::assertFalse($again->created());
        static::assertFalse($again->associated());
        static::assertTrue($again->alreadyAssociated());
        static::assertSame($account->employeeNumber(), $again->account()?->employeeNumber());
    }

    public function testAnUnknownEmployeeWithoutCreationDataIsRefused(): void
    {
        $gateway = SimulatorSamples::gateway();

        $rejection = SimulatorSamples::rejection(static fn(): EmployeeAssociation => $gateway->associateEmployee(
            SimulatorSamples::tenant(),
            SimulatorSamples::employer(),
            SimulatorSamples::byNumber(),
            EmployeeCreation::of(null, null, null, null, null, null),
        ));

        static::assertSame(Operation::AssociateEmployee, $rejection->operation());
        static::assertSame(RejectionKind::InvalidParameter, $rejection->kind());
        static::assertTrue($rejection->hasErrorCode('PARAM_INVALIDE'));
    }

    public function testAnAssociationRefusesAnUnknownEmployerAndABirthDateThatDiffers(): void
    {
        $gateway = SimulatorSamples::gateway();
        $creation = EmployeeCreation::of(ActivityType::homeCare(), CalendarDate::fromString('1980-01-30'), null, null, null, null);

        $unknown = SimulatorSamples::rejection(static fn(): EmployeeAssociation => $gateway->associateEmployee(SimulatorSamples::tenant(), SimulatorSamples::employer(SimulatorSamples::OTHER_EMPLOYER_NUMBER), SimulatorSamples::byNumber(), $creation));
        $mismatch = SimulatorSamples::rejection(static fn(): EmployeeAssociation => $gateway->associateEmployee(SimulatorSamples::tenant(), SimulatorSamples::employer(SimulatorSamples::EMPLOYER_NUMBER, '1976-02-23'), SimulatorSamples::byNumber(), $creation));

        static::assertSame(Operation::AssociateEmployee, $unknown->operation());
        static::assertSame(RejectionKind::EmployerNotFound, $unknown->kind());
        static::assertTrue($unknown->hasErrorCode('PE_INEXISTANT'));
        static::assertSame('Employeur inexistant', $unknown->errors()->first()?->message());
        static::assertTrue($mismatch->hasErrorCode('ER_API_020_0000'));
    }

    public function testAnEmployeeIdentifiedByNumberIsComparedWithTheNumberOfTheSimulator(): void
    {
        $gateway = new SimulatedPajeGateway();
        $gateway->knowEmployee(SimulatorSamples::employeeNumber(), true, true);

        $account = $gateway->verifyEmployee(SimulatorSamples::tenant(), EmployeeByNumber::of(SimulatorSamples::employeeNumber(), CalendarDate::fromString('1990-03-04')));

        static::assertTrue($account->childminder());
        static::assertTrue($account->homeCare());
    }
}
