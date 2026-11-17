<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderContractEnd;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareContractEnd;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;

/**
 * Request of operations 045 / 046 (pre-declaration) and 050 / 051 (declaration), which share their body
 * (`InputDeclarationAma` / `InputDeclarationGed`, api-reference §2.7 to §2.10): the path (and so the choice between
 * pre-declaring and declaring) is {@see ApiPaths}, the employer birth date is a query parameter.
 *
 * Optional values that are not set are omitted; `finDeContrat` is omitted when there is no contract end. The key order
 * is the one of the official examples. The query (`dtNaissanceEmployeur`) is {@see EmployerQueryMapper::query()}.
 */
final class DeclarationRequestMapper
{
    /**
     * @return array<string, mixed>
     *
     * @throws InvariantViolation "declaration_request.unsupported" for a declaration that is neither AMA nor GED
     */
    public static function body(#[SensitiveParameter] Declaration $declaration): array
    {
        return match (true) {
            $declaration instanceof ChildminderDeclaration => self::childminder($declaration),
            $declaration instanceof HomeCareDeclaration => self::homeCare($declaration),
            default => throw InvariantViolation::because(
                'declaration_request.unsupported',
                'The declaration is neither a childminder nor a home childcare declaration.',
            ),
        };
    }

    /**
     * Query of 045 / 046 / 050 / 051: the employer birth date, see {@see EmployerQueryMapper::query()}.
     *
     * @return array{dtNaissanceEmployeur: string}
     */
    public static function query(#[SensitiveParameter] EmployerReference $employer): array
    {
        return EmployerQueryMapper::query($employer);
    }

    /**
     * @return array<string, mixed>
     */
    private static function childminder(ChildminderDeclaration $declaration): array
    {
        $contractEnd = $declaration->contractEnd();

        return OptionalWireValues::present([
            'inputDeclCommun' => DeclarationCommonMapper::encode($declaration->common()),
            'inputDeclEnfant' => array_map(ChildminderChildMapper::encode(...), $declaration->children()),
            'inputSp' => DeclaredEmployeeMapper::encode($declaration->employee()),
            'finDeContrat' => $contractEnd instanceof ChildminderContractEnd
                ? ChildminderContractEndMapper::encode($contractEnd)
                : null,
            'mntIndemnitesEntretien' => OptionalWireValues::amount($declaration->maintenanceAllowance()),
            'mntIndemnitesRepas' => OptionalWireValues::amount($declaration->mealAllowance()),
            'nbHeuresComplementaires' => $declaration->complementaryHours(),
            'nbHeuresMajorees' => $declaration->majoredHours(),
            'nbJoursActivite' => $declaration->activityDays(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function homeCare(HomeCareDeclaration $declaration): array
    {
        $contractEnd = $declaration->contractEnd();

        return OptionalWireValues::present([
            'cdModeCalcul' => $declaration->calculationMode()->value(),
            'inputDeclCommun' => DeclarationCommonMapper::encode($declaration->common()),
            'inputDeclEnfant' => array_map(ChildMapper::encode(...), $declaration->children()),
            'inputSp' => DeclaredEmployeeMapper::encode($declaration->employee()),
            'finDeContrat' => $contractEnd instanceof HomeCareContractEnd
                ? HomeCareContractEndMapper::encode($contractEnd)
                : null,
            'mntFraisTransport' => OptionalWireValues::amount($declaration->transportCosts()),
            'nbHrSupMajA25' => $declaration->overtimeHours25(),
            'nbHrSupMajA50' => $declaration->overtimeHours50(),
        ]);
    }
}
