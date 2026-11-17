<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Service;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\SubmissionStage;

final class SubmissionStageTest extends TestCase
{
    public function testOnlyADeclarationMayHaveBeenRecordedByUrssaf(): void
    {
        static::assertTrue(SubmissionStage::Declaration->mayHaveRecorded());
        static::assertFalse(SubmissionStage::PreDeclaration->mayHaveRecorded());
    }
}
