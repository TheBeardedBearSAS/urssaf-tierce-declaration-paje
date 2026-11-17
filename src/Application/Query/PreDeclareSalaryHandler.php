<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\PreDeclarationView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\MandateRequired;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\SalaryPreDeclarer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\MandateGuard;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationActivity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;

/**
 * Pre-declares a salary (045 / 046). A query: nothing is recorded. The mandate guard applies (ADR 0009).
 */
final readonly class PreDeclareSalaryHandler
{
    public function __construct(private SalaryPreDeclarer $preDeclarer, private MandateGuard $guard) {}

    /**
     * @throws MandateRequired            "mandate.required" (nothing was sent)
     * @throws InvariantViolation         "pre_declaration.unsupported" for a declaration that is neither a childminder nor a
     *                                    home care declaration
     * @throws UrssafRequestRejected
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable
     * @throws RateLimitExceeded
     */
    public function __invoke(PreDeclareSalary $query): PreDeclarationView
    {
        $declaration = $query->declaration;
        $this->guard->assertMandate(
            $query->tenantId,
            $query->employer->number(),
            match ($declaration->activityType()) {
                DeclarationActivity::Childminder => Operation::PreDeclareChildminder,
                DeclarationActivity::HomeCare => Operation::PreDeclareHomeCare,
            },
            $query->skipMandateCheck,
        );

        return PreDeclarationView::fromResult(match (true) {
            $declaration instanceof ChildminderDeclaration => $this->preDeclarer->preDeclareChildminder(
                $query->tenantId,
                $query->employer,
                $declaration,
            ),
            $declaration instanceof HomeCareDeclaration => $this->preDeclarer->preDeclareHomeCare(
                $query->tenantId,
                $query->employer,
                $declaration,
            ),
            default => throw InvariantViolation::because(
                'pre_declaration.unsupported',
                'The declaration is neither a childminder nor a home care declaration.',
            ),
        });
    }
}
