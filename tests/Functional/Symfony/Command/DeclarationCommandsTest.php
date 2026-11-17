<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationActivity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationSnapshot;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Input\DeclarationFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Scenario;

/**
 * @mago-expect lint:too-many-methods
 */
final class DeclarationCommandsTest extends TestCase
{
    private const DECLARE = 'urssaf:paje:declare';
    private const PREDECLARE = 'urssaf:paje:predeclare';
    private const NUMBER = 'Y1869005780009';

    /** The declared record of the success fixture, without its record id. */
    private const DECLARED = [
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
        'Outcome' => 'declared',
    ];

    /**
     * @return array<string, string>
     */
    private static function childminder(): array
    {
        return [
            'employer-number' => self::NUMBER,
            '--reference' => Scenario::DECLARATION,
            '--activity' => 'childminder',
            '--payload' => Fields::payload('childminder_declaration.json'),
        ];
    }

    public function testAChildminderSalaryIsDeclared(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::DECLARE);
        $command->setInputs(['1975-02-23']);

        $status = $command->execute(self::childminder());

        static::assertSame(0, $status, $command->getDisplay());
        $fields = Fields::of($command->getDisplay());
        static::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $fields['Record id']);
        unset($fields['Record id']);
        static::assertSame(self::DECLARED, $fields);
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_050));
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_051));
    }

    public function testAHomeCareSalaryIsDeclared(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::DECLARE);
        $command->setInputs(['1975-02-23']);

        static::assertSame(0, $command->execute(['--activity' => 'home_care', '--payload' => Fields::payload('home_care_declaration.json')] + self::childminder()));

        static::assertSame('home_care', Fields::of($command->getDisplay())['Activity']);
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_051));
    }

    public function testADeclarationIsReplayedWithoutANewCall(): void
    {
        $harness = Harness::boot();
        $first = $harness->command(self::DECLARE);
        $first->setInputs(['1975-02-23']);
        static::assertSame(0, $first->execute(self::childminder()));
        $command = $harness->command(self::DECLARE);
        $command->setInputs(['1975-02-23']);

        static::assertSame(0, $command->execute(self::childminder()));

        static::assertSame('replayed', Fields::of($command->getDisplay())['Outcome']);
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_050));
    }

    public function testAnotherContentUnderTheSameReferenceExitsWithOne(): void
    {
        $harness = Harness::boot();
        Scenario::declaration($harness);
        $command = $harness->command(self::DECLARE);
        $command->setInputs(['1975-02-23']);

        static::assertSame(1, $command->execute(self::childminder()));

        static::assertStringContainsString('The external reference is already recorded with another declaration content or employer.', $command->getDisplay());
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_050));
    }

    public function testADefinitiveRefusalExitsWithOne(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_050, 400, 'error_param_invalide.json');
        $command = $harness->command(self::DECLARE);
        $command->setInputs(['1975-02-23']);

        static::assertSame(1, $command->execute(self::childminder()));

        $fields = Fields::of($command->getDisplay());
        unset($fields['Record id']);
        static::assertStringContainsString('Fix the data (see the error codes) and declare it under a new reference.', $command->getDisplay());
        static::assertSame([
            'External reference' => Scenario::DECLARATION,
            'Activity' => 'childminder',
            'Period' => '2026-10-01 / 2026-10-31',
            'State' => 'rejected',
            'Unknown outcome reason' => '-',
            'Attempts' => '1',
            'Error codes' => 'PARAM_INVALIDE (invalid_parameter)',
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
            'Outcome' => 'rejected',
        ], $fields);
    }

    public function testADuplicateExitsWithOne(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_050, 400, 'error_duplicate_declaration_0070.json');
        $command = $harness->command(self::DECLARE);
        $command->setInputs(['1975-02-23']);

        static::assertSame(1, $command->execute(self::childminder()));

        static::assertSame('already_declared', Fields::of($command->getDisplay())['State']);
        static::assertStringContainsString('URSSAF already holds a declaration for this key: see urssaf:paje:declaration:show.', $command->getDisplay());
    }

    public function testADeclarationBeingSentByAnotherProcessIsNotSentAgain(): void
    {
        $harness = Harness::boot();
        // @mago-expect analysis:mixed-assignment
        $payload = json_decode((string) file_get_contents(Fields::payload('childminder_declaration.json')), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($payload);
        Scenario::staleSnapshot($harness, DeclarationSnapshot::fromDeclaration(
            PajeEmployerNumber::of(self::NUMBER),
            (new DeclarationFactory())->fromInput(DeclarationActivity::Childminder, $payload),
        ));
        $command = $harness->command(self::DECLARE);
        $command->setInputs(['1975-02-23']);

        static::assertSame(1, $command->execute(self::childminder()));

        static::assertSame('submitting', Fields::of($command->getDisplay())['State'], $command->getDisplay());
        static::assertStringContainsString('It is being sent by another process: wait, then run urssaf:paje:declarations:reconcile-stale.', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_050));
    }

    public function testALostAnswerExitsWithOneAndPointsToTheResolution(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_050, 500, '{}');
        $command = $harness->command(self::DECLARE);
        $command->setInputs(['1975-02-23']);

        static::assertSame(1, $command->execute(self::childminder()));

        static::assertSame('outcome_unknown', Fields::of($command->getDisplay())['State']);
        static::assertStringContainsString('The answer was lost: never send it again. Check on pajemploi.urssaf.fr, then run urssaf:paje:declaration:resolve.', $command->getDisplay());
    }

    public function testInvalidCredentialsExitWithOneAndPointToTheRequeue(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_AUTHENTICATION, 401, '{"error":"invalid_client"}');
        $command = $harness->command(self::DECLARE);
        $command->setInputs(['1975-02-23']);

        static::assertSame(1, $command->execute(self::childminder()));

        static::assertSame('invalid_credentials', Fields::of($command->getDisplay())['State']);
        static::assertStringContainsString('urssaf:paje:declarations:requeue-invalid-credentials', $command->getDisplay());
    }

    public function testARetryableFailureExitsWithOneAndSaysWhenToRetry(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_050, 429, '{}', ['Retry-After' => '30']);
        $command = $harness->command(self::DECLARE);
        $command->setInputs(['1975-02-23']);

        static::assertSame(1, $command->execute(self::childminder()));

        static::assertStringContainsString('Nothing was recorded as sent: run the command again in 30 second(s) or more.', $command->getDisplay());
    }

    public function testTheMandateGuardIsReportedAndCanBeSkipped(): void
    {
        $harness = Harness::boot(['workflow' => ['require_local_mandate' => true]]);
        Scenario::importedEmployer($harness);
        $refused = $harness->command(self::DECLARE);
        $refused->setInputs(['1975-02-23']);

        static::assertSame(1, $refused->execute(self::childminder()));
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_050));

        $skipped = $harness->command(self::DECLARE);
        $skipped->setInputs(['1975-02-23']);
        static::assertSame(0, $skipped->execute(self::childminder() + ['--skip-mandate-check' => true]));
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_050));
    }

    /**
     * @param array<string, string> $overrides
     */
    #[DataProvider('invalidUsages')]
    public function testAnInvalidUsageExitsWithTwoBeforeAnyPromptOrCall(array $overrides, string $message): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::DECLARE);
        $command->setInputs(['1975-02-23']);

        static::assertSame(2, $command->execute($overrides + self::childminder()));

        static::assertStringContainsString($message, $command->getDisplay());
        static::assertStringNotContainsString('birth date', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_050));
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_051));
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function invalidUsages(): iterable
    {
        yield 'empty reference' => [['--reference' => ''], '(external_reference.empty)'];
        yield 'unknown activity' => [['--activity' => 'nanny'], 'The --activity option must be "childminder" or "home_care".'];
        yield 'missing file' => [['--payload' => 'missing.json'], 'The --payload file cannot be read, or is larger than 1 MiB.'];
        yield 'invalid file' => [['--payload' => Fields::payload('invalid.json')], 'The --payload file is not valid JSON.'];
        yield 'scalar file' => [['--payload' => Fields::payload('scalar.json')], 'The --payload file must hold a JSON object or list.'];
        yield 'a file that is not a declaration' => [['--payload' => Fields::payload('children.json')], 'Invalid input'];
        yield 'a home care file for a childminder' => [['--payload' => Fields::payload('home_care_declaration.json')], 'activity_days'];
    }

    public function testTheReferenceIsRequired(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::DECLARE);
        $input = self::childminder();
        unset($input['--reference']);

        static::assertSame(2, $command->execute($input));

        static::assertStringContainsString('The --reference option is required.', $command->getDisplay());
    }

    public function testTheEmployerBirthDateIsValidated(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::DECLARE);
        $command->setInputs(['1975-14-45']);

        static::assertSame(2, $command->execute(self::childminder()));

        static::assertStringContainsString('birth_date', $command->getDisplay());
        static::assertStringNotContainsString('1975-14-45', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_050));
    }

    public function testItNeedsAnInteractiveSession(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::DECLARE);

        static::assertSame(2, $command->execute(self::childminder(), ['interactive' => false]));

        static::assertStringContainsString('Employer birth date (YYYY-MM-DD) is read from a hidden prompt: run the command interactively.', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_050));
    }

    public function testASalaryIsPreDeclaredWithoutRecordingAnything(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::PREDECLARE);
        $command->setInputs(['1975-02-23']);

        $status = $command->execute(['--tenant' => Scenario::TENANT] + array_diff_key(self::childminder(), ['--reference' => true]));

        static::assertSame(0, $status, $command->getDisplay());
        static::assertSame([
            'Contributions (€)' => '0.00',
            'Covered by family benefits (€)' => '0.00',
            'Employer share (€)' => '0.00',
            'Payable to employee (€)' => '0.00',
            'Payable by Pajemploi (€)' => '0.00',
            'Debited by Pajemploi (€)' => '0.00',
            'Planned debit date' => '2020-01-01',
            'Planned payment date' => '2020-01-31',
            'Pajemploi+' => 'no',
            'Transfer date' => '-',
            'Child checks' => '1',
            'Non-blocking codes' => 'ER_API_020_NB_0000',
        ], Fields::of($command->getDisplay()));
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_045));
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_050));
        $list = $harness->command('urssaf:paje:declarations:list');
        static::assertSame(0, $list->execute(['--state' => 'pending']));
        static::assertStringContainsString('0 declaration(s).', $list->getDisplay());
    }

    public function testAHomeCareSalaryIsPreDeclared(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::PREDECLARE);
        $command->setInputs(['1975-02-23']);

        static::assertSame(0, $command->execute(['--activity' => 'home_care', '--payload' => Fields::payload('home_care_declaration.json'), 'employer-number' => self::NUMBER]));

        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_046));
    }

    /**
     * @param array<string, string> $overrides
     */
    #[DataProvider('invalidPreDeclarationUsages')]
    public function testAnInvalidPreDeclarationExitsWithTwoBeforeAnyPromptOrCall(array $overrides, string $message): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::PREDECLARE);
        $command->setInputs(['1975-02-23']);

        static::assertSame(2, $command->execute($overrides + array_diff_key(self::childminder(), ['--reference' => true])));

        static::assertStringContainsString($message, $command->getDisplay());
        static::assertStringNotContainsString('birth date', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_045));
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function invalidPreDeclarationUsages(): iterable
    {
        yield 'unknown activity' => [['--activity' => 'nanny'], 'The --activity option must be "childminder" or "home_care".'];
        yield 'no file' => [['--payload' => ''], 'The --payload file cannot be read, or is larger than 1 MiB.'];
        yield 'a file that is not a declaration' => [['--payload' => Fields::payload('children.json')], 'Invalid input'];
    }

    public function testThePreDeclarationNeedsAMandateWhenRequiredUnlessSkipped(): void
    {
        $harness = Harness::boot(['workflow' => ['require_local_mandate' => true]]);
        $input = array_diff_key(self::childminder(), ['--reference' => true]);
        $refused = $harness->command(self::PREDECLARE);
        $refused->setInputs(['1975-02-23']);

        static::assertSame(1, $refused->execute($input));
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_045));

        $skipped = $harness->command(self::PREDECLARE);
        $skipped->setInputs(['1975-02-23']);
        static::assertSame(0, $skipped->execute($input + ['--skip-mandate-check' => true]));
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_045));
    }

    public function testARefusalOfUrssafOnAPreDeclarationExitsWithOne(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_045, 400, 'error_param_invalide.json');
        $command = $harness->command(self::PREDECLARE);
        $command->setInputs(['1975-02-23']);

        static::assertSame(1, $command->execute(array_diff_key(self::childminder(), ['--reference' => true])));
    }
}
