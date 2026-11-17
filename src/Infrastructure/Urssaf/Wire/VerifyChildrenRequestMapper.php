<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;

/**
 * 022 `POST /employeurs/{numeroPajeEmployeur}/enfants/verifier` (api-reference §2.3): path
 * {@see ApiPaths::verifyChildren()}, query `dtNaissanceEmployeur`, body `InputVerificationEnfantPe`.
 */
final class VerifyChildrenRequestMapper
{
    /**
     * @return array{dtNaissanceEmployeur: string}
     */
    public static function query(#[SensitiveParameter] EmployerReference $employer): array
    {
        return EmployerQueryMapper::query($employer);
    }

    /**
     * The list is sent as given, in order; the "at least one child" rule belongs to the caller.
     *
     * @param list<Child> $children
     *
     * @return array{inputEnfantPe: list<array{dtNaissanceEnfant: string, nomEnfant: string, prenomEnfant: string}>}
     */
    public static function body(#[SensitiveParameter] array $children): array
    {
        return ['inputEnfantPe' => array_map(ChildMapper::encode(...), $children)];
    }
}
