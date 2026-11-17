<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;

final class DeclarationRecordIdTest extends TestCase
{
    public function testKeepsTheValueVerbatim(): void
    {
        $id = DeclarationRecordId::fromString(' rec-1 ');

        static::assertSame(' rec-1 ', $id->value());
        static::assertSame(' rec-1 ', (string) $id);
    }

    public function testAcceptsTheMaximumLength(): void
    {
        $value = str_repeat('é', DeclarationRecordId::MAX_LENGTH);

        static::assertSame($value, DeclarationRecordId::fromString($value)->value());
    }

    public function testRejectsABlankValue(): void
    {
        RecordSamples::assertViolation(
            'declaration_record_id.empty',
            static fn(): DeclarationRecordId => DeclarationRecordId::fromString("  \n"),
        );
    }

    public function testRejectsATooLongValue(): void
    {
        RecordSamples::assertViolation(
            'declaration_record_id.too_long',
            static fn(): DeclarationRecordId => DeclarationRecordId::fromString(str_repeat('a', DeclarationRecordId::MAX_LENGTH + 1)),
        );
    }

    public function testEquality(): void
    {
        static::assertTrue(DeclarationRecordId::fromString('a')->equals(DeclarationRecordId::fromString('a')));
        static::assertFalse(DeclarationRecordId::fromString('a')->equals(DeclarationRecordId::fromString('b')));
    }
}
