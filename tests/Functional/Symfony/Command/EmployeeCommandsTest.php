<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\HttpScript;

/**
 * @mago-expect lint:too-many-methods
 */
final class EmployeeCommandsTest extends TestCase
{
    private const VERIFY = 'urssaf:paje:employee:verify';
    private const ASSOCIATE = 'urssaf:paje:employee:associate';
    private const NUMBER = 'Z1234567890123';
    private const NIR = '275037512345678';

    public function testAnEmployeeIsVerifiedByNumber(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::VERIFY);

        $status = $command->execute(['employee-number' => self::NUMBER]);

        static::assertSame(0, $status, $command->getDisplay());
        static::assertSame(['Childminder' => 'yes', 'Home care' => 'yes', 'Online account' => 'Actif'], Fields::of($command->getDisplay()));
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_021));
        static::assertStringNotContainsString(self::NUMBER, $command->getDisplay());
        static::assertStringNotContainsString('hidden', $command->getDisplay());
    }

    public function testAnEmployeeIsVerifiedByNirAskedInAHiddenPrompt(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::VERIFY);
        $command->setInputs([self::NIR]);

        static::assertSame(0, $command->execute(["--nir" => true]), $command->getDisplay());

        static::assertStringContainsString('Employee NIR: ', $command->getDisplay());
        static::assertStringNotContainsString(self::NIR, $command->getDisplay());
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_021));
    }

    public function testTheBirthDateIsAskedOnRequest(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::VERIFY);
        $command->setInputs([self::NIR, '1990-03-04']);

        static::assertSame(0, $command->execute(['--nir' => true, '--employee-birth-date' => true]));

        static::assertStringContainsString('Employee birth date (YYYY-MM-DD): ', $command->getDisplay());
        static::assertStringNotContainsString('1990-03-04', $command->getDisplay());
    }

    public function testAnEmployeeIsVerifiedByIdentityFile(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::VERIFY);

        static::assertSame(0, $command->execute(['--identity-payload' => Fields::payload('identity.json')]));

        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_021));
        static::assertStringNotContainsString('MARTIN', $command->getDisplay());
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('invalidUsages')]
    public function testAnInvalidUsageExitsWithTwoAndNeverCallsUrssaf(array $input, string $message): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::VERIFY);
        $command->setInputs(['275037512345678', '1990-03-04']);

        static::assertSame(2, $command->execute($input));

        static::assertStringContainsString($message, $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_021));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidUsages(): iterable
    {
        $exactlyOne = 'Give exactly one of the employee-number argument, --nir or --identity-payload.';
        yield 'nothing' => [[], $exactlyOne];
        yield 'number and NIR' => [['employee-number' => self::NUMBER, '--nir' => true], $exactlyOne];
        yield 'number and file' => [['employee-number' => self::NUMBER, '--identity-payload' => 'identity.json'], $exactlyOne];
        yield 'NIR and file' => [['--nir' => true, '--identity-payload' => 'identity.json'], $exactlyOne];
        yield 'birth date with a file' => [['--identity-payload' => Fields::payload('identity.json'), '--employee-birth-date' => true], '--employee-birth-date cannot be combined with --identity-payload'];
        yield 'malformed number' => [['employee-number' => 'x'], 'employee_number'];
        yield 'missing file' => [['--identity-payload' => 'missing.json'], 'The --identity-payload file cannot be read, or is larger than 1 MiB.'];
        yield 'invalid file' => [['--identity-payload' => Fields::payload('invalid.json')], 'The --identity-payload file is not valid JSON.'];
        yield 'empty identity' => [['--identity-payload' => Fields::payload('children.json')], 'employee_identification'];
    }

    public function testAMalformedNirOrBirthDateIsRefusedWithoutBeingPrinted(): void
    {
        $nir = Harness::boot()->command(self::VERIFY);
        $nir->setInputs(['SECRETNIR12345']);
        $birth = Harness::boot()->command(self::VERIFY);
        $birth->setInputs([self::NIR, '1990-14-45']);

        static::assertSame(2, $nir->execute(['--nir' => true]));
        static::assertSame(2, $birth->execute(['--nir' => true, '--employee-birth-date' => true]));

        static::assertStringContainsString('nir', $nir->getDisplay());
        static::assertStringContainsString('birth_date', $birth->getDisplay());
        static::assertStringNotContainsString('SECRETNIR12345', $nir->getDisplay());
        static::assertStringNotContainsString('1990-14-45', $birth->getDisplay());
    }

    public function testItNeedsAnInteractiveSessionForTheNir(): void
    {
        $command = Harness::boot()->command(self::VERIFY);

        static::assertSame(2, $command->execute(['--nir' => true], ['interactive' => false]));

        static::assertStringContainsString('Employee NIR is read from a hidden prompt: run the command interactively.', $command->getDisplay());
    }

    public function testAnUnavailablePlatformExitsWithOne(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_021, 503, '{}');
        $command = $harness->command(self::VERIFY);

        static::assertSame(1, $command->execute(['employee-number' => self::NUMBER]));
    }

    public function testAnEmployeeIsAssociatedAndCreated(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::ASSOCIATE);
        $command->setInputs(['1975-02-23', self::NIR]);

        static::assertSame(0, $command->execute(['employer-number' => 'Y1869005780009', '--nir' => true, '--creation-payload' => Fields::payload('creation.json')]));

        static::assertSame([
            'Created' => 'yes',
            'Associated' => 'yes',
            'Already associated' => 'no',
            'Childminder' => 'yes',
            'Home care' => 'yes',
            'Online account' => 'Actif',
            'Non-blocking codes' => 'ER_API_020_NB_0000',
        ], Fields::of($command->getDisplay()));
        static::assertStringContainsString('Employer birth date (YYYY-MM-DD): ', $command->getDisplay());
        static::assertStringNotContainsString("n'est pas actif", $command->getDisplay());
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_023));
    }

    public function testAnEmployeeIsAssociatedByNumberWithoutCreationData(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::ASSOCIATE);
        $command->setInputs(['1975-02-23']);

        static::assertSame(0, $command->execute(['employer-number' => 'Y1869005780009', 'employee-number' => self::NUMBER]));

        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_023));
    }

    public function testAnInvalidCreationFileExitsWithTwoBeforeAnyPrompt(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::ASSOCIATE);

        static::assertSame(2, $command->execute(['employer-number' => 'Y1869005780009', 'employee-number' => self::NUMBER, '--creation-payload' => Fields::payload('invalid.json')]));

        static::assertStringContainsString('The --creation-payload file is not valid JSON.', $command->getDisplay());
        static::assertStringNotContainsString('birth date', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_023));
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('invalidEmployeeSelectionsOfAssociate')]
    public function testAnInvalidEmployeeSelectionExitsWithTwoBeforeTheEmployerPrompt(array $input): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::ASSOCIATE);

        static::assertSame(2, $command->execute(['employer-number' => 'Y1869005780009'] + $input));

        static::assertStringNotContainsString('Employer birth date', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_023));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidEmployeeSelectionsOfAssociate(): iterable
    {
        yield 'no selector' => [[]];
        yield 'two selectors' => [['employee-number' => self::NUMBER, '--nir' => true]];
        yield 'invalid identity file' => [['--identity-payload' => Fields::payload('invalid.json')]];
        yield 'unreadable identity file' => [['--identity-payload' => Fields::payload('missing.json')]];
    }

    public function testAnInvalidCreationDataExitsWithTwo(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::ASSOCIATE);

        static::assertSame(2, $command->execute(['employer-number' => 'Y1869005780009', 'employee-number' => self::NUMBER, '--creation-payload' => Fields::payload('children.json')]));

        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_023));
    }

    public function testAnAlreadyAssociatedEmployeeIsNotAnError(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_023, 200, '{"erreursNonBloquantes":[{"code":"ER_API_023_NB_0000","description":"d","message":"m"}],"outputVerificationSp":{"etatCompteEnLigne":"Actif","indcAma":true,"indcGed":false},"salarieAssocieOk":false,"salarieCreeOk":false,"salarieDejaAssocieOK":true}');
        $command = $harness->command(self::ASSOCIATE);
        $command->setInputs(['1975-02-23']);

        static::assertSame(0, $command->execute(['employer-number' => 'Y1869005780009', 'employee-number' => self::NUMBER]));

        $fields = Fields::of($command->getDisplay());
        static::assertSame('yes', $fields['Already associated']);
        static::assertSame('no', $fields['Home care']);
        static::assertSame('ER_API_023_NB_0000', $fields['Non-blocking codes']);
    }

    public function testAnAssociationWithoutAnAccountShowsDashes(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_023, 200, '{"salarieAssocieOk":true,"salarieCreeOk":false,"salarieDejaAssocieOK":false}');
        $command = $harness->command(self::ASSOCIATE);
        $command->setInputs(['1975-02-23']);

        static::assertSame(0, $command->execute(['employer-number' => 'Y1869005780009', 'employee-number' => self::NUMBER]));

        static::assertSame([
            'Created' => 'no',
            'Associated' => 'yes',
            'Already associated' => 'no',
            'Childminder' => '-',
            'Home care' => '-',
            'Online account' => '-',
            'Non-blocking codes' => '-',
        ], Fields::of($command->getDisplay()));
    }

    public function testARefusalOfUrssafExitsWithOne(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_023, 400, 'error_param_invalide.json');
        $command = $harness->command(self::ASSOCIATE);
        $command->setInputs(['1975-02-23']);

        static::assertSame(1, $command->execute(['employer-number' => 'Y1869005780009', 'employee-number' => self::NUMBER]));
    }
}
