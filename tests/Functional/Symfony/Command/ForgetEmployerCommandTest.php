<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetEmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetEmployerAccountHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Scenario;

final class ForgetEmployerCommandTest extends TestCase
{
    private const NAME = 'urssaf:paje:employer:forget';

    private static function isForgotten(Harness $harness): bool
    {
        try {
            $harness->get(GetEmployerAccountHandler::class)(new GetEmployerAccount(TenantId::fromString(Scenario::TENANT), EmployerSelector::byExternalReference(ExternalReference::fromString(Scenario::EMPLOYER))));
        } catch (RecordNotFound) {
            return true;
        }

        return false;
    }

    public function testAnEmployerIsErasedAfterAConfirmation(): void
    {
        $harness = Harness::boot();
        $account = Scenario::importedEmployer($harness);
        $command = $harness->command(self::NAME);
        $command->setInputs(['yes']);

        static::assertSame(0, $command->execute(['--employer' => Scenario::EMPLOYER]));

        $display = $command->getDisplay();
        static::assertStringContainsString('Erase this employer, its mandates and its declaration records from the local store? This cannot be undone and nothing is sent to URSSAF. [y/N]', $display);
        static::assertSame([
            'Account id' => $account->accountId,
            'Mandates erased' => '0',
            'Declarations erased' => '0',
        ], Fields::of($display));
        static::assertStringContainsString('Erased from the local store only: nothing was sent to URSSAF.', $display);
        static::assertTrue(self::isForgotten($harness));
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    public function testNothingIsErasedWithoutConfirmation(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness);
        $command = $harness->command(self::NAME);
        $command->setInputs(['no']);

        static::assertSame(0, $command->execute(['--employer' => Scenario::EMPLOYER]));

        static::assertStringContainsString('Aborted: nothing was erased.', $command->getDisplay());
        static::assertFalse(self::isForgotten($harness));
    }

    public function testAnEmptyAnswerIsNotAConfirmation(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness);
        $command = $harness->command(self::NAME);
        $command->setInputs(['']);

        static::assertSame(0, $command->execute(['--employer' => Scenario::EMPLOYER]));

        static::assertStringContainsString('Aborted: nothing was erased.', $command->getDisplay());
        static::assertFalse(self::isForgotten($harness));
    }

    public function testForceSkipsTheConfirmation(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--employer' => Scenario::EMPLOYER, '--force' => true], ['interactive' => false]));

        static::assertStringNotContainsString('[y/N]', $command->getDisplay());
        static::assertTrue(self::isForgotten($harness));
    }

    public function testItNeedsAConfirmationWhenNotInteractive(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(2, $command->execute(['--employer' => Scenario::EMPLOYER], ['interactive' => false]));

        static::assertStringContainsString('Erasing an employer needs a confirmation: use --force when not interactive.', $command->getDisplay());
        static::assertFalse(self::isForgotten($harness));
    }

    public function testAnActiveMandateRefusesTheErasureUnlessAllowed(): void
    {
        $harness = Harness::boot();
        Scenario::mandatedEmployer($harness);

        $refused = $harness->command(self::NAME);
        static::assertSame(1, $refused->execute(['--employer' => Scenario::EMPLOYER, '--force' => true]));
        static::assertFalse(self::isForgotten($harness));

        $allowed = $harness->command(self::NAME);
        static::assertSame(0, $allowed->execute(['--employer' => Scenario::EMPLOYER, '--force' => true, '--allow-active-mandate' => true]));
        static::assertSame('1', Fields::of($allowed->getDisplay())['Mandates erased']);
        static::assertTrue(self::isForgotten($harness));
    }

    public function testAnUnknownOutcomeRefusesTheErasureUnlessAllowed(): void
    {
        $harness = Harness::boot();
        Scenario::outcomeUnknownDeclaration($harness);

        $refused = $harness->command(self::NAME);
        static::assertSame(1, $refused->execute(['--employer' => Scenario::EMPLOYER, '--force' => true, '--allow-active-mandate' => true]));

        $allowed = $harness->command(self::NAME);
        static::assertSame(0, $allowed->execute(['--employer' => Scenario::EMPLOYER, '--force' => true, '--allow-active-mandate' => true, '--allow-unknown-outcomes' => true]));
        static::assertSame('1', Fields::of($allowed->getDisplay())['Declarations erased']);
    }

    public function testAnInvalidSelectorExitsWithTwo(): void
    {
        $command = Harness::boot()->command(self::NAME);

        static::assertSame(2, $command->execute(['--force' => true]));

        static::assertStringContainsString('Give one of --employer, --employer-number or --account-id.', $command->getDisplay());
    }

    public function testAnUnknownEmployerExitsWithOne(): void
    {
        $command = Harness::boot()->command(self::NAME);

        static::assertSame(1, $command->execute(['--employer' => 'unknown', '--force' => true]));
    }
}
