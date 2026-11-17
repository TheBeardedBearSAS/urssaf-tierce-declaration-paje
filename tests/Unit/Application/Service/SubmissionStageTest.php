<?php

declare(strict_types=1);

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
