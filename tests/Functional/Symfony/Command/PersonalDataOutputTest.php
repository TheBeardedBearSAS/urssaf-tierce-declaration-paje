<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Scenario;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;

/**
 * No command prints, and nothing logs, a birth date, a NIR, a name, an employer or employee number, or the message and the
 * description of an URSSAF error (ADR 0007, ADR 0013): in the normal output and with -vvv.
 */
final class PersonalDataOutputTest extends TestCase
{
    private const BIRTH_DATE = '1975-02-23';
    private const NIR = '275037512345678';
    private const BAD_BIRTH_DATE = '1975-14-45';
    private const BAD_NIR = 'SECRETNIR12345';
    private const EMPLOYER_NUMBER = 'Y1869005780009';
    private const EMPLOYEE_NUMBER = 'Z1234567890123';

    /** @var array<string, mixed> */
    private array $options = [];

    /** @var list<string> */
    private array $displays = [];

    /** What must never be printed or logged. */
    private const PERSONAL = [
        self::BIRTH_DATE,
        self::NIR,
        self::BAD_BIRTH_DATE,
        self::BAD_NIR,
        self::EMPLOYER_NUMBER,
        self::EMPLOYEE_NUMBER,
        '280017501234567',
        '1990-03-04',
        '2015-01-12',
        'DUPONT',
        'CAMILLE',
        'LOUIS',
        'MARTIN',
        'SOPHIE',
        "L'HOMME",
        'JEAN-MICHEL',
        "n'est pas actif",
        "L'inscription au site",
        'est différente de la date de naissance',
        'dateNaissance est invalide',
        'ne correspondent pas',
    ];

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('verbosities')]
    public function testNoCommandPrintsPersonalData(array $options): void
    {
        $harness = Harness::boot(['outbox' => ['enabled' => true, 'sink' => 'event_dispatcher']]);
        $this->options = $options;
        $this->displays = [];
        $declaration = ['--activity' => 'childminder', '--payload' => Fields::payload('childminder_declaration.json')];

        $this->execute($harness, 'urssaf:paje:employer:track', ['employer-number' => self::EMPLOYER_NUMBER, '--reference' => Scenario::EMPLOYER], [self::BIRTH_DATE]);
        $this->execute($harness, 'urssaf:paje:employer:import', ['employer-number' => self::EMPLOYER_NUMBER, '--reference' => 'employer-2'], [self::BIRTH_DATE]);
        $this->execute($harness, 'urssaf:paje:employer:verify', ['employer-number' => self::EMPLOYER_NUMBER], [self::BIRTH_DATE]);
        $this->execute($harness, 'urssaf:paje:employee:verify', ['--nir' => true, '--employee-birth-date' => true], [self::NIR, '1990-03-04']);
        $this->execute($harness, 'urssaf:paje:employee:verify', ['employee-number' => self::EMPLOYEE_NUMBER]);
        $this->execute($harness, 'urssaf:paje:employee:verify', ['--identity-payload' => Fields::payload('identity.json')]);
        $this->execute($harness, 'urssaf:paje:employee:associate', ['employer-number' => self::EMPLOYER_NUMBER, '--nir' => true, '--creation-payload' => Fields::payload('creation.json')], [self::BIRTH_DATE, self::NIR]);
        $this->execute($harness, 'urssaf:paje:children:verify', ['employer-number' => self::EMPLOYER_NUMBER, '--payload' => Fields::payload('children.json')], [self::BIRTH_DATE]);
        $this->execute($harness, 'urssaf:paje:estimate', ['--activity' => 'childminder', '--payload' => Fields::payload('childminder_estimate.json')]);
        $this->execute($harness, 'urssaf:paje:predeclare', ['employer-number' => self::EMPLOYER_NUMBER] + $declaration, [self::BIRTH_DATE]);
        $this->execute($harness, 'urssaf:paje:declare', ['employer-number' => self::EMPLOYER_NUMBER, '--reference' => Scenario::DECLARATION] + $declaration, [self::BIRTH_DATE]);
        $this->execute($harness, 'urssaf:paje:declaration:show', ['--declaration' => Scenario::DECLARATION]);
        $this->execute($harness, 'urssaf:paje:declarations:list', ['--state' => 'declared']);
        $this->execute($harness, 'urssaf:paje:mandate:register', ['--employer-number' => self::EMPLOYER_NUMBER]);
        $this->execute($harness, 'urssaf:paje:mandate:import', ['--employer-number' => self::EMPLOYER_NUMBER]);
        $this->execute($harness, 'urssaf:paje:employers:sync-status', ['--employer-number' => self::EMPLOYER_NUMBER, '--tenant' => Scenario::TENANT]);
        $this->execute($harness, 'urssaf:paje:employers:sync-status', []);
        $this->execute($harness, 'urssaf:paje:employers:sync-status', ['--dry-run' => true]);
        $this->execute($harness, 'urssaf:paje:mandate:cancel', ['--employer-number' => self::EMPLOYER_NUMBER, '--force' => true]);
        $this->execute($harness, 'urssaf:paje:mandate:record-cancellation', ['--employer-number' => self::EMPLOYER_NUMBER]);
        $this->execute($harness, 'urssaf:paje:declarations:reconcile-stale', []);
        $this->execute($harness, 'urssaf:paje:declarations:requeue-invalid-credentials', []);
        $this->execute($harness, 'urssaf:paje:outbox:relay', []);
        $this->execute($harness, 'urssaf:paje:outbox:purge', []);
        $harness->urssaf->respond(HttpScript::ROUTE_050, 500, '{}');
        $this->execute($harness, 'urssaf:paje:declare', ['employer-number' => self::EMPLOYER_NUMBER, '--reference' => 'declaration-2'] + $declaration, [self::BIRTH_DATE]);
        $this->execute($harness, 'urssaf:paje:declaration:resolve', ['--declaration' => 'declaration-2', '--not-received' => true]);
        $this->execute($harness, 'urssaf:paje:employer:forget', ['--employer-number' => self::EMPLOYER_NUMBER, '--force' => true, '--allow-active-mandate' => true, '--allow-unknown-outcomes' => true]);

        self::assertNothingPersonal(implode("\n", $this->displays) . $harness->privateService('logger', CapturingLogger::class)->dump());
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('verbosities')]
    public function testNoFailureMessagePrintsPersonalData(array $options): void
    {
        $harness = Harness::boot();
        $this->options = $options;
        $this->displays = [];
        $declaration = ['--activity' => 'childminder', '--payload' => Fields::payload('childminder_declaration.json')];

        $this->execute($harness, 'urssaf:paje:employer:verify', ['employer-number' => self::EMPLOYER_NUMBER], [self::BAD_BIRTH_DATE]);
        $this->execute($harness, 'urssaf:paje:employer:track', ['employer-number' => 'x' . self::EMPLOYER_NUMBER, '--reference' => 'employer-1'], [self::BAD_BIRTH_DATE]);
        $this->execute($harness, 'urssaf:paje:employee:verify', ['--nir' => true], [self::BAD_NIR]);
        $this->execute($harness, 'urssaf:paje:employee:verify', ['--nir' => true, '--employee-birth-date' => true], [self::NIR, self::BAD_BIRTH_DATE]);
        $this->execute($harness, 'urssaf:paje:employee:verify', ['--identity-payload' => Fields::payload('children.json')]);
        $this->execute($harness, 'urssaf:paje:children:verify', ['employer-number' => self::EMPLOYER_NUMBER, '--payload' => Fields::payload('childminder_estimate.json')], [self::BIRTH_DATE]);
        $this->execute($harness, 'urssaf:paje:declare', ['employer-number' => self::EMPLOYER_NUMBER, '--reference' => 'declaration-1', '--activity' => 'childminder', '--payload' => Fields::payload('home_care_declaration.json')], [self::BIRTH_DATE]);
        $this->execute($harness, 'urssaf:paje:declare', ['employer-number' => self::EMPLOYER_NUMBER, '--reference' => 'declaration-1'] + $declaration, [self::BAD_BIRTH_DATE]);
        $this->execute($harness, 'urssaf:paje:declare', ['employer-number' => self::EMPLOYER_NUMBER, '--reference' => 'declaration-1'] + $declaration);

        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_020, 400, 'error_birth_date_mismatch.json');
        $this->execute($harness, 'urssaf:paje:employer:verify', ['employer-number' => self::EMPLOYER_NUMBER], [self::BIRTH_DATE]);
        $this->execute($harness, 'urssaf:paje:employer:track', ['employer-number' => self::EMPLOYER_NUMBER, '--reference' => 'employer-1'], [self::BIRTH_DATE]);
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_021, 400, 'error_param_invalide.json');
        $this->execute($harness, 'urssaf:paje:employee:verify', ['--nir' => true], [self::NIR]);
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_022, 400, 'error_param_invalide.json');
        $this->execute($harness, 'urssaf:paje:children:verify', ['employer-number' => self::EMPLOYER_NUMBER, '--payload' => Fields::payload('children.json')], [self::BIRTH_DATE]);
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_023, 400, 'error_param_invalide.json');
        $this->execute($harness, 'urssaf:paje:employee:associate', ['employer-number' => self::EMPLOYER_NUMBER, '--nir' => true], [self::BIRTH_DATE, self::NIR]);
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_040, 400, 'error_param_invalide.json');
        $this->execute($harness, 'urssaf:paje:estimate', ['--activity' => 'childminder', '--payload' => Fields::payload('childminder_estimate.json')]);
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_045, 400, 'error_birth_date_mismatch.json');
        $this->execute($harness, 'urssaf:paje:predeclare', ['employer-number' => self::EMPLOYER_NUMBER] + $declaration, [self::BIRTH_DATE]);
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_050, 400, 'error_birth_date_mismatch.json');
        $this->execute($harness, 'urssaf:paje:declare', ['employer-number' => self::EMPLOYER_NUMBER, '--reference' => 'declaration-1'] + $declaration, [self::BIRTH_DATE]);
        $this->execute($harness, 'urssaf:paje:declaration:show', ['--declaration' => 'declaration-1']);
        $this->execute($harness, 'urssaf:paje:declarations:list', ['--state' => 'rejected']);

        self::assertNothingPersonal(implode("\n", $this->displays) . $harness->privateService('logger', CapturingLogger::class)->dump());
    }

    /**
     * @param array<string, mixed> $input
     * @param list<string>         $answers
     */
    private function execute(Harness $harness, string $name, array $input, array $answers = []): void
    {
        $command = $harness->command($name);
        $command->setInputs($answers);
        $command->execute($input, $this->options);
        $this->displays[] = $command->getDisplay();
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function verbosities(): iterable
    {
        yield 'normal' => [[]];
        yield 'debug (-vvv)' => [['verbosity' => OutputInterface::VERBOSITY_DEBUG]];
    }

    private static function assertNothingPersonal(string $text): void
    {
        foreach (self::PERSONAL as $personal) {
            static::assertStringNotContainsString($personal, $text);
        }
        static::assertStringContainsString('Employer birth date (YYYY-MM-DD): ', $text, 'The commands did print something.');
    }
}
