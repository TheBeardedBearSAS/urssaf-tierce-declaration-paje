<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\SubmissionError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class SubmissionErrorTest extends TestCase
{
    public function testKeepsEveryFieldVerbatim(): void
    {
        $error = SubmissionError::of('ER_API_050_0070', 'duplicate_declaration', 'Message', 'Description');

        static::assertSame('ER_API_050_0070', $error->code());
        static::assertSame('duplicate_declaration', $error->kind());
        static::assertSame('Message', $error->message());
        static::assertSame('Description', $error->description());
    }

    public function testMessageAndDescriptionAreOptional(): void
    {
        $error = SubmissionError::of('ERR_TECHNIQUE', 'technical', null, null);

        static::assertNull($error->message());
        static::assertNull($error->description());
    }

    public function testRejectsABlankCode(): void
    {
        RecordSamples::assertViolation(
            'submission_error_code.empty',
            static fn(): SubmissionError => SubmissionError::of(' ', 'technical', null, null),
        );
    }

    public function testRejectsABlankKind(): void
    {
        RecordSamples::assertViolation(
            'submission_error_kind.empty',
            static fn(): SubmissionError => SubmissionError::of('CODE', '  ', null, null),
        );
    }

    public function testMasksTheDescriptionInDebugDumps(): void
    {
        DomainAssertions::assertSensitiveParameters(SubmissionError::class, 'of', ['description']);
        static::assertSame(
            ['code' => 'C', 'kind' => 'k', 'message' => 'M', 'description' => '***'],
            SubmissionError::of('C', 'k', 'M', 'secret')->__debugInfo(),
        );
        static::assertNull(SubmissionError::of('C', 'k', 'M', null)->__debugInfo()['description']);
    }
}
