<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

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
