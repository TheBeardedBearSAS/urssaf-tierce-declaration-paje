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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;

/**
 * The options that designate a tracked employer or a declaration, and their reading. The values go through the value
 * objects: an invalid one is an invalid input (exit code 2).
 */
final class CommandSelectors
{
    private const OPTION_EMPLOYER = 'employer';
    private const OPTION_EMPLOYER_NUMBER = 'employer-number';
    private const OPTION_ACCOUNT_ID = 'account-id';
    private const OPTION_DECLARATION = 'declaration';
    private const OPTION_RECORD_ID = 'record-id';

    /**
     * --employer=<external reference> | --employer-number=<Pajemploi number> | --account-id=<account id>
     */
    public static function addEmployerSelector(Command $command): void
    {
        $command->addOption(self::OPTION_EMPLOYER, null, InputOption::VALUE_REQUIRED, 'External reference of the employer');
        $command->addOption(self::OPTION_EMPLOYER_NUMBER, null, InputOption::VALUE_REQUIRED, 'Pajemploi number of the employer');
        $command->addOption(self::OPTION_ACCOUNT_ID, null, InputOption::VALUE_REQUIRED, 'Id of the employer account');
    }

    /**
     * --declaration=<external reference> | --record-id=<record id>
     */
    public static function addDeclarationSelector(Command $command): void
    {
        $command->addOption(self::OPTION_DECLARATION, null, InputOption::VALUE_REQUIRED, 'External reference of the declaration');
        $command->addOption(self::OPTION_RECORD_ID, null, InputOption::VALUE_REQUIRED, 'Id of the declaration record');
    }

    /**
     * Exactly one of --employer, --employer-number and --account-id.
     *
     * @throws InvalidOptionException when none or several are given
     */
    public static function employerSelector(InputInterface $input): EmployerSelector
    {
        return self::optionalEmployerSelector($input) ?? throw self::employerSelectorRequired();
    }

    /**
     * Same, null when none is given.
     *
     * @throws InvalidOptionException when several are given
     */
    public static function optionalEmployerSelector(InputInterface $input): ?EmployerSelector
    {
        $reference = CommandOptions::text($input, self::OPTION_EMPLOYER);
        $employerNumber = CommandOptions::text($input, self::OPTION_EMPLOYER_NUMBER);
        $accountId = CommandOptions::text($input, self::OPTION_ACCOUNT_ID);
        $given = \count(array_filter([$reference, $employerNumber, $accountId], static fn(?string $value): bool => $value !== null));
        if ($given > 1) {
            throw self::employerSelectorRequired();
        }

        return match (true) {
            $reference !== null => EmployerSelector::byExternalReference(ExternalReference::fromString($reference)),
            $employerNumber !== null => EmployerSelector::byEmployerNumber(PajeEmployerNumber::of($employerNumber)),
            $accountId !== null => EmployerSelector::byAccountId(EmployerAccountId::fromString($accountId)),
            default => null,
        };
    }

    /**
     * Exactly one of --declaration and --record-id.
     *
     * @throws InvalidOptionException when none or both are given
     */
    public static function declarationSelector(InputInterface $input): DeclarationSelector
    {
        $reference = CommandOptions::text($input, self::OPTION_DECLARATION);
        $recordId = CommandOptions::text($input, self::OPTION_RECORD_ID);

        return match (true) {
            $reference !== null && $recordId === null => DeclarationSelector::byExternalReference(ExternalReference::fromString($reference)),
            $recordId !== null && $reference === null => DeclarationSelector::byRecordId(DeclarationRecordId::fromString($recordId)),
            default => throw new InvalidOptionException('Give one of --declaration or --record-id.'),
        };
    }

    private static function employerSelectorRequired(): InvalidOptionException
    {
        return new InvalidOptionException('Give one of --employer, --employer-number or --account-id.');
    }
}
