<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildEntitlement;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\VerifyChildrenRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\VerifyChildrenResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\EmployeeWireSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\WireAssertions;

final class VerifyChildrenMappersTest extends TestCase
{
    public function testTheRequestIsTheOfficialExample(): void
    {
        static::assertSame(['dtNaissanceEmployeur' => '1975-02-23'], VerifyChildrenRequestMapper::query(EmployeeWireSamples::employer()));
        static::assertSame(
            json_encode(Fixtures::json('verify_children_request'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            JsonEncoder::encode(VerifyChildrenRequestMapper::body([EmployeeWireSamples::child()])),
        );
    }

    public function testTheChildrenKeepTheirOrderAndAnEmptyListStaysAList(): void
    {
        $body = VerifyChildrenRequestMapper::body([EmployeeWireSamples::child(), EmployeeWireSamples::child()]);

        static::assertCount(2, $body['inputEnfantPe']);
        static::assertSame('{"inputEnfantPe":[]}', JsonEncoder::encode(VerifyChildrenRequestMapper::body([])));
    }

    public function testTheOfficialResponseIsMapped(): void
    {
        $checks = VerifyChildrenResponseMapper::map(JsonDecoder::decode(Fixtures::load('verify_children_response')));

        static::assertCount(1, $checks);
        static::assertSame(ChildEntitlement::Entitled, $checks[0]->status()->documented());
        static::assertTrue(WireAssertions::present($checks[0]->child())->equals(EmployeeWireSamples::child()));
    }

    public function testItemsWithoutStatusAreSkippedAndTheOrderIsKept(): void
    {
        $checks = VerifyChildrenResponseMapper::map(JsonDecoder::decode(
            '{"verificationEnfantPe":[{"reponseStatutEnfant":2},{},{"reponseStatutEnfant":0}]}',
        ));

        static::assertCount(2, $checks);
        static::assertSame(2, $checks[0]->status()->raw());
        static::assertSame(0, $checks[1]->status()->raw());
        static::assertSame([0, 1], array_keys($checks));
    }

    public function testAnAbsentListIsEmpty(): void
    {
        static::assertSame([], VerifyChildrenResponseMapper::map(JsonDecoder::decode('{}')));
        static::assertSame([], VerifyChildrenResponseMapper::map(JsonDecoder::decode('{"verificationEnfantPe":[]}')));
    }

    public function testAMalformedListIsRefused(): void
    {
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "verificationEnfantPe" is not a JSON array.',
            static fn(): mixed => VerifyChildrenResponseMapper::map(JsonDecoder::decode('{"verificationEnfantPe":"x"}')),
        );
    }

    public function testAnEchoedChildWithAPlaceholderDateIsNoChild(): void
    {
        $checks = VerifyChildrenResponseMapper::map(JsonDecoder::decode(
            '{"verificationEnfantPe":[{"reponseStatutEnfant":1,"inputEnfantPe":{"dtNaissanceEnfant":"string","nomEnfant":"A","prenomEnfant":"B"}}]}',
        ));

        static::assertCount(1, $checks);
        static::assertNull($checks[0]->child());
    }

    public function testWhatIsNotAJsonObjectIsRefused(): void
    {
        WireAssertions::assertRefusesWhatIsNotAnObject(VerifyChildrenResponseMapper::map(...));
    }
}
