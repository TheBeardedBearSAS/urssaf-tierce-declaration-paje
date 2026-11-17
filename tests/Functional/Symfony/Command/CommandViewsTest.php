<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\AssociateEmployee;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\AssociateEmployeeHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployeeAssociationView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerAccountView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\PreDeclarationView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetEmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetEmployerAccountHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\PreDeclareSalary;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\PreDeclareSalaryHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\CommandViews;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Input\EmployeeCreationFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Scenario;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Testing\SimulatorSamples;

/**
 * The rows of the views, from views read outside any application (a property read on a missing part is a warning here).
 *
 * @mago-expect lint:too-many-methods
 */
final class CommandViewsTest extends TestCase
{
    /**
     * @param list<array{string, string}> $rows
     *
     * @return array<array-key, string>
     */
    private static function map(array $rows): array
    {
        return array_column($rows, 1, 0);
    }

    private static function refreshed(): EmployerAccountView
    {
        $harness = Harness::boot();
        Scenario::mandatedEmployer($harness);
        $harness->command('urssaf:paje:employers:sync-status')->execute([]);

        return $harness->get(GetEmployerAccountHandler::class)(new GetEmployerAccount(TenantId::fromString(Scenario::TENANT), EmployerSelector::byExternalReference(ExternalReference::fromString(Scenario::EMPLOYER))));
    }

    private static function association(string $body): EmployeeAssociationView
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_023, 200, $body);

        return $harness->get(AssociateEmployeeHandler::class)(new AssociateEmployee(
            TenantId::fromString(Scenario::TENANT),
            SimulatorSamples::employer(),
            SimulatorSamples::byNumber(),
            (new EmployeeCreationFactory())->fromInput([]),
        ));
    }

    private static function preDeclaration(string $body): PreDeclarationView
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_045, 200, $body);

        return $harness->get(PreDeclareSalaryHandler::class)(new PreDeclareSalary(TenantId::fromString(Scenario::TENANT), SimulatorSamples::employer(), SimulatorSamples::childminder()));
    }

    private static function declared(): DeclarationView
    {
        return Scenario::declaration(Harness::boot())->declaration;
    }

    private static function unknown(): DeclarationView
    {
        return Scenario::outcomeUnknownDeclaration(Harness::boot())->declaration;
    }

    public function testAnAssociationWithoutAnAccountShowsDashes(): void
    {
        $view = self::association('{"salarieAssocieOk":true,"salarieCreeOk":false,"salarieDejaAssocieOK":false}');

        static::assertSame([
            'Created' => 'no',
            'Associated' => 'yes',
            'Already associated' => 'no',
            'Childminder' => '-',
            'Home care' => '-',
            'Online account' => '-',
            'Non-blocking codes' => '-',
        ], self::map(CommandViews::association($view)));
    }

    public function testAnAssociationWithAnAccountShowsIt(): void
    {
        $view = self::association('{"erreursNonBloquantes":[{"code":"A","description":"d","message":"m"},{"code":"B"}],"outputVerificationSp":{"etatCompteEnLigne":"Actif","indcAma":true,"indcGed":false},"salarieAssocieOk":false,"salarieCreeOk":true,"salarieDejaAssocieOK":true}');

        static::assertSame([
            'Created' => 'yes',
            'Associated' => 'no',
            'Already associated' => 'yes',
            'Childminder' => 'yes',
            'Home care' => 'no',
            'Online account' => 'Actif',
            'Non-blocking codes' => 'A, B',
        ], self::map(CommandViews::association($view)));
    }

    public function testAnAccountThatWasNeverRefreshedShowsDashes(): void
    {
        $account = Scenario::importedEmployer(Harness::boot());

        static::assertSame([
            'Account id' => $account->accountId,
            'External reference' => Scenario::EMPLOYER,
            'Stage' => 'tracked',
            'Authorised' => '-',
            'Online account' => '-',
            'Mandate state' => '-',
            'Last checked at' => '-',
        ], self::map(CommandViews::refreshedAccount($account)));
    }

    public function testARefreshedAccountWithAMandateShowsThem(): void
    {
        $refreshed = self::refreshed();

        static::assertSame([
            'Account id' => $refreshed->accountId,
            'External reference' => Scenario::EMPLOYER,
            'Stage' => 'mandate_registered',
            'Authorised' => 'yes',
            'Online account' => 'Actif',
            'Mandate state' => 'registered',
            'Last checked at' => '2026-11-15T10:00:00Z',
        ], self::map(CommandViews::refreshedAccount($refreshed)));
    }

    public function testAPreDeclarationWithoutAmountsShowsDashes(): void
    {
        $view = self::preDeclaration('{}');

        static::assertSame([
            'Contributions (€)' => '-',
            'Covered by family benefits (€)' => '-',
            'Employer share (€)' => '-',
            'Payable to employee (€)' => '-',
            'Payable by Pajemploi (€)' => '-',
            'Debited by Pajemploi (€)' => '-',
            'Planned debit date' => '-',
            'Planned payment date' => '-',
            'Pajemploi+' => 'no',
            'Transfer date' => '-',
            'Child checks' => '0',
            'Non-blocking codes' => '-',
        ], self::map(CommandViews::preDeclaration($view)));
    }

    public function testADeclaredRecordShowsItsResult(): void
    {
        $view = self::declared();

        static::assertSame([
            'Record id' => $view->recordId,
            'External reference' => Scenario::DECLARATION,
            'Activity' => 'childminder',
            'Period' => '2026-10-01 / 2026-10-31',
            'State' => 'declared',
            'Unknown outcome reason' => '-',
            'Attempts' => '1',
            'Error codes' => '-',
            'Document reference' => 'string',
            'Contributions (€)' => '0.00',
            'Covered by family benefits (€)' => '0.00',
            'Employer share (€)' => '0.00',
            'Payable to employee (€)' => '0.00',
            'Payable by Pajemploi (€)' => '0.00',
            'Debited by Pajemploi (€)' => '0.00',
            'Planned debit date' => '2020-01-01',
            'Planned payment date' => '2020-01-31',
            'Transfer date' => '-',
            'Non-blocking codes' => 'ER_API_020_NB_0000',
            'Updated at' => '2026-11-15T10:00:00Z',
        ], self::map(CommandViews::declaration($view)));
    }

    public function testARecordWithoutAResultShowsDashes(): void
    {
        $view = self::unknown();

        static::assertSame([
            'Record id' => $view->recordId,
            'External reference' => Scenario::DECLARATION,
            'Activity' => 'childminder',
            'Period' => '2026-10-01 / 2026-10-31',
            'State' => 'outcome_unknown',
            'Unknown outcome reason' => 'may_have_reached_urssaf',
            'Attempts' => '1',
            'Error codes' => '-',
            'Document reference' => '-',
            'Contributions (€)' => '-',
            'Covered by family benefits (€)' => '-',
            'Employer share (€)' => '-',
            'Payable to employee (€)' => '-',
            'Payable by Pajemploi (€)' => '-',
            'Debited by Pajemploi (€)' => '-',
            'Planned debit date' => '-',
            'Planned payment date' => '-',
            'Transfer date' => '-',
            'Non-blocking codes' => '-',
            'Updated at' => '2026-11-15T10:00:00Z',
        ], self::map(CommandViews::declaration($view)));
    }
}
