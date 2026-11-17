<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeIdentification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Input\EmployeeIdentificationFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\Input\EmployerReferenceFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;

/**
 * The personal inputs of the commands (ADR 0007, ADR 0013): the employer birth date, the employee NIR and the employee
 * birth date come from a hidden prompt, never from the arguments (shell history, process list, cron logs); an identity
 * (names, birth place) comes from a JSON file. They go through the Domain input factories, so that an invalid value is an
 * invalid input (exit code 2) whose message never repeats the value.
 */
final class CommandPersonalInputs
{
    private const ARGUMENT_EMPLOYER_NUMBER = 'employer-number';
    private const ARGUMENT_EMPLOYEE_NUMBER = 'employee-number';
    private const OPTION_NIR = 'nir';
    private const OPTION_EMPLOYEE_BIRTH_DATE = 'employee-birth-date';
    private const OPTION_IDENTITY_PAYLOAD = 'identity-payload';

    /**
     * The "employer-number" argument: the birth date of the employer is asked in a hidden prompt.
     */
    public static function addEmployer(Command $command): void
    {
        $command->addArgument(self::ARGUMENT_EMPLOYER_NUMBER, InputArgument::REQUIRED, 'Pajemploi number of the employer (its birth date is asked in a hidden prompt)');
    }

    /**
     * The employee given by exactly one of the "employee-number" argument, --nir (hidden prompt) or --identity-payload.
     */
    public static function addEmployee(Command $command): void
    {
        $command->addArgument(self::ARGUMENT_EMPLOYEE_NUMBER, InputArgument::OPTIONAL, 'Pajemploi number of the employee');
        $command->addOption(self::OPTION_NIR, null, InputOption::VALUE_NONE, 'Give the NIR of the employee instead (asked in a hidden prompt)');
        $command->addOption(self::OPTION_EMPLOYEE_BIRTH_DATE, null, InputOption::VALUE_NONE, 'Also give the birth date of the employee (asked in a hidden prompt, with the number or the NIR)');
        CommandPayloads::add($command, self::OPTION_IDENTITY_PAYLOAD, 'JSON file with the identity of the employee (instead of the number or the NIR): see Domain\Employee\Input\EmployeeIdentificationFactory');
    }

    /**
     * @throws InvalidOptionException in non-interactive mode, or without an answer to the prompt
     * @throws ValidationFailed       for an invalid number or birth date
     */
    public static function employer(InputInterface $input, OutputInterface $output, QuestionHelper $helper): EmployerReference
    {
        $number = CommandOptions::argument($input, self::ARGUMENT_EMPLOYER_NUMBER) ?? '';

        return (new EmployerReferenceFactory())->fromInput([
            'employer_number' => $number,
            'birth_date' => CommandPrompts::hiddenValue($input, $output, $helper, 'Employer birth date (YYYY-MM-DD)'),
        ]);
    }

    /**
     * The prompt-free step: checks that the number, --nir and --identity-payload are given exactly once, that
     * --employee-birth-date is compatible, and reads the identity file.
     *
     * @throws InvalidOptionException for an invalid selection or an unusable file
     */
    public static function selectEmployee(InputInterface $input): EmployeeSelection
    {
        $number = CommandOptions::argument($input, self::ARGUMENT_EMPLOYEE_NUMBER);
        $nir = $input->getOption(self::OPTION_NIR) === true;
        $birthDate = $input->getOption(self::OPTION_EMPLOYEE_BIRTH_DATE) === true;
        if (\count(array_filter([$number !== null, $nir, CommandOptions::text($input, self::OPTION_IDENTITY_PAYLOAD) !== null])) !== 1) {
            throw new InvalidOptionException('Give exactly one of the employee-number argument, --nir or --identity-payload.');
        }
        $identity = CommandPayloads::read($input, self::OPTION_IDENTITY_PAYLOAD);
        if ($identity !== null && $birthDate) {
            throw new InvalidOptionException('--employee-birth-date cannot be combined with --identity-payload: put "birth_date" in the file.');
        }

        return new EmployeeSelection($number, $nir, $birthDate, $identity);
    }

    /**
     * The prompt step, after {@see self::selectEmployee()} (and the other prompt-free checks of the command).
     *
     * @throws InvalidOptionException in non-interactive mode, or without an answer to a prompt
     * @throws ValidationFailed       for an invalid value
     */
    public static function promptEmployee(EmployeeSelection $selection, InputInterface $input, OutputInterface $output, QuestionHelper $helper): EmployeeIdentification
    {
        if ($selection->identity !== null) {
            return (new EmployeeIdentificationFactory())->fromInput($selection->identity);
        }

        $fields = $selection->number !== null ? ['employee_number' => $selection->number] : ['nir' => CommandPrompts::hiddenValue($input, $output, $helper, 'Employee NIR')];
        if ($selection->askBirthDate) {
            $fields['birth_date'] = CommandPrompts::hiddenValue($input, $output, $helper, 'Employee birth date (YYYY-MM-DD)');
        }

        return (new EmployeeIdentificationFactory())->fromInput($fields);
    }

    /**
     * Both steps, for the commands with no other prompt.
     *
     * @throws InvalidOptionException for an invalid selection, in non-interactive mode, or without an answer to a prompt
     * @throws ValidationFailed       for an invalid value
     */
    public static function employee(InputInterface $input, OutputInterface $output, QuestionHelper $helper): EmployeeIdentification
    {
        return self::promptEmployee(self::selectEmployee($input), $input, $output, $helper);
    }
}
