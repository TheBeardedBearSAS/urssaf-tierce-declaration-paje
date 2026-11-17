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
final class ChildrenAndEstimateCommandsTest extends TestCase
{
    private const CHILDREN = 'urssaf:paje:children:verify';
    private const ESTIMATE = 'urssaf:paje:estimate';
    private const NUMBER = 'Y1869005780009';

    public function testTheChildrenAreCheckedInTheOrderOfTheFile(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::CHILDREN);
        $command->setInputs(['1975-02-23']);

        $status = $command->execute(['employer-number' => self::NUMBER, '--payload' => Fields::payload('children.json')]);

        static::assertSame(0, $status, $command->getDisplay());
        static::assertStringContainsString('Employer birth date (YYYY-MM-DD): ', $command->getDisplay());
        static::assertSame(['Child 1' => '1 (Entitled)'], Fields::of($command->getDisplay()));
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_022));
        foreach (['DUPONT', 'CAMILLE', 'LOUIS', '2015-01-12', self::NUMBER] as $personal) {
            static::assertStringNotContainsString($personal, $command->getDisplay());
        }
    }

    /**
     * @param array<string, string> $input
     */
    #[DataProvider('invalidChildrenUsages')]
    public function testAnInvalidUsageExitsWithTwoBeforeAnyPromptOrCall(array $input, string $message): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::CHILDREN);
        $command->setInputs(['1975-02-23']);

        static::assertSame(2, $command->execute($input));

        static::assertStringContainsString($message, $command->getDisplay());
        static::assertStringNotContainsString('birth date', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_022));
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function invalidChildrenUsages(): iterable
    {
        yield 'no file' => [['employer-number' => self::NUMBER], 'The --payload option is required.'];
        yield 'missing file' => [['employer-number' => self::NUMBER, '--payload' => 'missing.json'], 'The --payload file cannot be read, or is larger than 1 MiB.'];
        yield 'invalid file' => [['employer-number' => self::NUMBER, '--payload' => Fields::payload('invalid.json')], 'The --payload file is not valid JSON.'];
        yield 'invalid child' => [['employer-number' => self::NUMBER, '--payload' => Fields::payload('childminder_estimate.json')], 'children['];
    }

    public function testTheEmployerBirthDateIsStillValidated(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::CHILDREN);
        $command->setInputs(['1975-14-45']);

        static::assertSame(2, $command->execute(['employer-number' => self::NUMBER, '--payload' => Fields::payload('children.json')]));

        static::assertStringContainsString('birth_date', $command->getDisplay());
        static::assertStringNotContainsString('1975-14-45', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_022));
    }

    public function testTheChildrenNeedAnInteractiveSession(): void
    {
        $command = Harness::boot()->command(self::CHILDREN);

        static::assertSame(2, $command->execute(['employer-number' => self::NUMBER, '--payload' => Fields::payload('children.json')], ['interactive' => false]));

        static::assertStringContainsString('is read from a hidden prompt', $command->getDisplay());
    }

    public function testARefusalOfUrssafExitsWithOne(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_022, 400, 'error_param_invalide.json');
        $command = $harness->command(self::CHILDREN);
        $command->setInputs(['1975-02-23']);

        static::assertSame(1, $command->execute(['employer-number' => self::NUMBER, '--payload' => Fields::payload('children.json')]));
    }

    public function testAChildminderEstimateIsAskedFromAFile(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::ESTIMATE);

        $status = $command->execute(['--activity' => 'childminder', '--payload' => Fields::payload('childminder_estimate.json')]);

        static::assertSame(0, $status, $command->getDisplay());
        static::assertSame([
            'Covered by family benefits (€)' => '0.00',
            'Employer share (€)' => '0.00',
            'Employer exemption (€)' => '0.00',
            'Non-blocking codes' => 'ER_API_020_NB_0000',
        ], Fields::of($command->getDisplay()));
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_040));
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_041));
        static::assertStringNotContainsString("n'est pas actif", $command->getDisplay());
    }

    public function testAHomeCareEstimateIsAskedFromAFile(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::ESTIMATE);

        static::assertSame(0, $command->execute(['--activity' => 'home_care', '--payload' => Fields::payload('home_care_estimate.json')]));

        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_041));
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_040));
    }

    /**
     * @param array<string, string> $input
     */
    #[DataProvider('invalidEstimateUsages')]
    public function testAnInvalidEstimateExitsWithTwoAndNeverCallsUrssaf(array $input, string $message): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::ESTIMATE);

        static::assertSame(2, $command->execute($input));

        static::assertStringContainsString($message, $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_040));
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_041));
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function invalidEstimateUsages(): iterable
    {
        yield 'no activity' => [['--payload' => Fields::payload('childminder_estimate.json')], 'The --activity option must be "childminder" or "home_care".'];
        yield 'no file' => [['--activity' => 'childminder'], 'The --payload option is required.'];
        yield 'an activity that does not fit the file' => [['--activity' => 'home_care', '--payload' => Fields::payload('childminder_estimate.json')], 'calculation_mode'];
        yield 'invalid file' => [['--activity' => 'childminder', '--payload' => Fields::payload('invalid.json')], 'The --payload file is not valid JSON.'];
    }

    public function testTheTenantOfAnEstimateIsRequiredWithSeveralTenants(): void
    {
        $command = Harness::boot(Accounts::withSecondTenant())->command(self::ESTIMATE);

        static::assertSame(2, $command->execute(['--activity' => 'childminder', '--payload' => Fields::payload('childminder_estimate.json')]));

        static::assertStringContainsString('The --tenant option is required (configured tenants: 2).', $command->getDisplay());
    }

    public function testARefusalOfUrssafOnAnEstimateExitsWithOne(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_040, 400, 'error_param_invalide.json');
        $command = $harness->command(self::ESTIMATE);

        static::assertSame(1, $command->execute(['--activity' => 'childminder', '--payload' => Fields::payload('childminder_estimate.json')]));
    }
}
