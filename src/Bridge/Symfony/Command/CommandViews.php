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

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\ChildCheckView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\ContributionEstimateView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationAmountsView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployeeAccountView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployeeAssociationView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerAccountView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerStatusView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\MandateView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\NonBlockingMessageView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\PreDeclarationView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\SubmissionErrorView;

/**
 * The rows of the views printed by the commands: identifiers, states, codes, amounts and instants only. Never an
 * employer or employee number, a name, a birth date, a NIR, nor the message or the description of an URSSAF error: they
 * echo host values (ADR 0007, ADR 0013).
 *
 * @mago-expect lint:too-many-methods One mapper per view printed by the commands: a flat catalogue.
 */
final class CommandViews
{
    /**
     * @return list<array{string, string}>
     */
    public static function employerStatus(EmployerStatusView $status): array
    {
        return [
            ['Authorised', CommandReports::yesNo($status->authorised)],
            ['Online account', $status->onlineAccountState],
            ['Checked at', CommandReports::orDash($status->checkedAt)],
        ];
    }

    /**
     * @return list<array{string, string}>
     */
    public static function employeeAccount(EmployeeAccountView $account): array
    {
        return [
            ['Childminder', CommandReports::yesNo($account->childminder)],
            ['Home care', CommandReports::yesNo($account->homeCare)],
            ['Online account', $account->onlineAccountState],
        ];
    }

    /**
     * @return list<array{string, string}>
     */
    public static function association(EmployeeAssociationView $association): array
    {
        return [
            ['Created', CommandReports::yesNo($association->created)],
            ['Associated', CommandReports::yesNo($association->associated)],
            ['Already associated', CommandReports::yesNo($association->alreadyAssociated)],
            ['Childminder', CommandReports::yesNo($association->account?->childminder)],
            ['Home care', CommandReports::yesNo($association->account?->homeCare)],
            ['Online account', CommandReports::orDash($association->account?->onlineAccountState)],
            ['Non-blocking codes', self::codes($association->messages)],
        ];
    }

    /**
     * The checks in the order of the children of the file.
     *
     * @param list<ChildCheckView> $checks
     *
     * @return list<array{string, string}>
     */
    public static function childChecks(array $checks): array
    {
        return array_map(
            static fn(ChildCheckView $check, int $index): array => [\sprintf('Child %d', $index + 1), \sprintf('%d (%s)', $check->status, CommandReports::orDash($check->entitlement))],
            $checks,
            array_keys($checks),
        );
    }

    /**
     * @return list<array{string, string}>
     */
    public static function estimate(ContributionEstimateView $estimate): array
    {
        return [
            ['Covered by family benefits (€)', CommandReports::orDash($estimate->coveredByFamilyBenefits)],
            ['Employer share (€)', CommandReports::orDash($estimate->employerShare)],
            ['Employer exemption (€)', CommandReports::orDash($estimate->employerExemption)],
            ['Non-blocking codes', self::codes($estimate->messages)],
        ];
    }

    /**
     * @return list<array{string, string}>
     */
    public static function preDeclaration(PreDeclarationView $result): array
    {
        return [
            ...self::amounts($result->amounts),
            ['Pajemploi+', CommandReports::yesNo($result->pajemploiPlus)],
            ['Transfer date', CommandReports::orDash($result->transferDate)],
            ['Child checks', (string) \count($result->childChecks)],
            ['Non-blocking codes', self::codes($result->messages)],
        ];
    }

    /**
     * The account as tracked (employer:track, employer:import).
     *
     * @return list<array{string, string}>
     */
    public static function trackedAccount(EmployerAccountView $account): array
    {
        return [
            ['Account id', $account->accountId],
            ['External reference', $account->externalReference],
            ['Origin', $account->origin],
            ['Stage', $account->stage],
            ['Created at', $account->createdAt],
        ];
    }

    /**
     * The account after a status refresh (employers:sync-status).
     *
     * @return list<array{string, string}>
     */
    public static function refreshedAccount(EmployerAccountView $account): array
    {
        return [
            ['Account id', $account->accountId],
            ['External reference', $account->externalReference],
            ['Stage', $account->stage],
            ['Authorised', CommandReports::yesNo($account->lastStatus?->authorised)],
            ['Online account', CommandReports::orDash($account->lastStatus?->onlineAccountState)],
            ['Mandate state', CommandReports::orDash($account->mandate?->state)],
            ['Last checked at', CommandReports::orDash($account->lastStatus?->checkedAt)],
        ];
    }

    /**
     * @return list<array{string, string}>
     */
    public static function mandate(MandateView $mandate): array
    {
        return [
            ['Mandate id', $mandate->mandateId],
            ['External reference', $mandate->externalReference],
            ['Account id', $mandate->employerAccountId],
            ['Origin', $mandate->origin],
            ['State', $mandate->state],
            ['Registered at', $mandate->registeredAt],
            ['Cancelled at', CommandReports::orDash($mandate->cancelledAt)],
        ];
    }

    /**
     * The state of a declaration record: error codes and kinds only, never the messages nor the descriptions of URSSAF.
     *
     * @return list<array{string, string}>
     */
    public static function declaration(DeclarationView $declaration): array
    {
        $status = $declaration->status;
        $result = $status->result;

        return [
            ['Record id', $declaration->recordId],
            ['External reference', $declaration->externalReference],
            ['Activity', $declaration->snapshot->activity],
            ['Period', \sprintf('%s / %s', $declaration->snapshot->periodStart, $declaration->snapshot->periodEnd)],
            ['State', $status->state],
            ['Unknown outcome reason', CommandReports::orDash($status->outcomeUnknownReason)],
            ['Attempts', (string) $status->attempts],
            ['Error codes', CommandReports::orDash($status->errors === [] ? null : implode(', ', array_map(static fn(SubmissionErrorView $error): string => \sprintf('%s (%s)', $error->code, $error->kind), $status->errors)))],
            ['Document reference', CommandReports::orDash($result?->documentReference)],
            ...self::amounts($result?->amounts),
            ['Transfer date', CommandReports::orDash($result?->transferDate)],
            ['Non-blocking codes', self::codes($result->messages ?? [])],
            ['Updated at', $status->updatedAt],
        ];
    }

    /**
     * @param list<NonBlockingMessageView> $messages
     */
    private static function codes(array $messages): string
    {
        return CommandReports::orDash($messages === [] ? null : implode(', ', array_map(static fn(NonBlockingMessageView $message): string => $message->code, $messages)));
    }

    /**
     * @return list<array{string, string}>
     */
    private static function amounts(?DeclarationAmountsView $amounts): array
    {
        return [
            ['Contributions (€)', CommandReports::orDash($amounts?->contributions)],
            ['Covered by family benefits (€)', CommandReports::orDash($amounts?->coveredByFamilyBenefits)],
            ['Employer share (€)', CommandReports::orDash($amounts?->employerShare)],
            ['Payable to employee (€)', CommandReports::orDash($amounts?->payableToEmployee)],
            ['Payable by Pajemploi (€)', CommandReports::orDash($amounts?->payableByPaje)],
            ['Debited by Pajemploi (€)', CommandReports::orDash($amounts?->debitedByPaje)],
            ['Planned debit date', CommandReports::orDash($amounts?->plannedDebitDate)],
            ['Planned payment date', CommandReports::orDash($amounts?->plannedPaymentDate)],
        ];
    }
}
