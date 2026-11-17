<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\DeclaredEmployee;

/**
 * A salary declaration or pre-declaration body (ADR 0004 sum type): {@see ChildminderDeclaration} (`InputDeclarationAma`)
 * or {@see HomeCareDeclaration} (`InputDeclarationGed`).
 */
interface Declaration
{
    public function common(): DeclarationCommon;

    public function employee(): DeclaredEmployee;

    /**
     * `inputDeclEnfant`: {@see ChildminderChild} items for a childminder, {@see Child} items for home childcare.
     *
     * @return list<ChildminderChild|Child>
     */
    public function children(): array;

    public function activityType(): DeclarationActivity;
}
