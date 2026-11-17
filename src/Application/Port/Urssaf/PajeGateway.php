<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf;

/**
 * Every operation of the API Tierce Déclaration PAJE, implemented by the infrastructure layer.
 */
interface PajeGateway extends
    EmployerVerifier,
    EmployeeVerifier,
    ChildrenVerifier,
    EmployeeAssociator,
    MandateRegistrar,
    ContributionEstimator,
    SalaryPreDeclarer,
    SalaryDeclarer {}
