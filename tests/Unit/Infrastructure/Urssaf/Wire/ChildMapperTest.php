<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\DomainException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\ChildMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\EmployeeWireSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\WireAssertions;

final class ChildMapperTest extends TestCase
{
    public function testTheChildIsEncodedAsTheOfficialExample(): void
    {
        $encoded = ChildMapper::encode(EmployeeWireSamples::child());

        static::assertSame(['inputEnfantPe' => [$encoded]], Fixtures::json('verify_children_request'));
        static::assertSame(['dtNaissanceEnfant', 'nomEnfant', 'prenomEnfant'], array_keys($encoded));
    }

    public function testTheOfficialChildIsDecoded(): void
    {
        $response = JsonDecoder::decode(Fixtures::load('verify_children_response'))->items('verificationEnfantPe');
        $child = ChildMapper::decode($response[0]->requireObject('inputEnfantPe'));

        static::assertTrue($child->equals(EmployeeWireSamples::child()));
    }

    public function testEncodingThenDecodingIsTheIdentity(): void
    {
        $json = JsonEncoder::encode(ChildMapper::encode(EmployeeWireSamples::child()));

        static::assertTrue(ChildMapper::decode(JsonDecoder::decode($json))->equals(EmployeeWireSamples::child()));
    }

    public function testTheBirthDatePlaceholderIsRefusedWithoutItsValue(): void
    {
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "dtNaissanceEnfant" is not a Y-m-d date.',
            static fn(): mixed => ChildMapper::decode(JsonDecoder::decode(
                '{"dtNaissanceEnfant":"string","nomEnfant":"A","prenomEnfant":"B"}',
            )),
        );
    }

    public function testAMissingFieldIsRefused(): void
    {
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "dtNaissanceEnfant" is missing or empty.',
            static fn(): mixed => ChildMapper::decode(JsonDecoder::decode('{"nomEnfant":"A","prenomEnfant":"B"}')),
        );
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "nomEnfant" is missing or empty.',
            static fn(): mixed => ChildMapper::decode(JsonDecoder::decode('{"dtNaissanceEnfant":"2015-01-12","prenomEnfant":"B"}')),
        );
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "prenomEnfant" is missing or empty.',
            static fn(): mixed => ChildMapper::decode(JsonDecoder::decode('{"dtNaissanceEnfant":"2015-01-12","nomEnfant":"A"}')),
        );
    }

    public function testAnInvalidNameIsRefusedWithoutItsValue(): void
    {
        $exception = WireAssertions::caught(static fn(): mixed => ChildMapper::decode(JsonDecoder::decode(
            '{"dtNaissanceEnfant":"2015-01-12","nomEnfant":"secret name","prenomEnfant":"B"}',
        )));

        static::assertSame('Unexpected URSSAF response: field "nomEnfant" is invalid.', $exception->getMessage());
        static::assertStringNotContainsString('secret', $exception->getMessage());
        static::assertInstanceOf(DomainException::class, $exception->getPrevious());
        static::assertSame('child_last_name.invalid_format', $exception->getPrevious()->violationCode());

        $first = WireAssertions::caught(static fn(): mixed => ChildMapper::decode(JsonDecoder::decode(
            '{"dtNaissanceEnfant":"2015-01-12","nomEnfant":"A","prenomEnfant":"é"}',
        )));
        static::assertInstanceOf(DomainException::class, $first->getPrevious());
        static::assertSame('child_first_name.invalid_format', $first->getPrevious()->violationCode());

        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "prenomEnfant" is invalid.',
            static fn(): mixed => ChildMapper::decode(JsonDecoder::decode(
                '{"dtNaissanceEnfant":"2015-01-12","nomEnfant":"A","prenomEnfant":"é"}',
            )),
        );
    }
}
