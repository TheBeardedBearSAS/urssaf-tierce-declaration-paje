<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\OnlineAccountStateValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\EmployeeAccountMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\Payload;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\WireAssertions;

final class EmployeeAccountMapperTest extends TestCase
{
    public function testTheOfficialExampleIsDecoded(): void
    {
        $account = EmployeeAccountMapper::decode(JsonDecoder::decode(Fixtures::load('verify_employee_response')));

        static::assertSame('string', $account->employeeNumber());
        static::assertTrue($account->childminder());
        static::assertTrue($account->homeCare());
        static::assertSame(OnlineAccountStateValue::Active, $account->state()->documented());
    }

    public function testEachBooleanIsReadIndependently(): void
    {
        $ama = EmployeeAccountMapper::decode(JsonDecoder::decode('{"indcAma":true,"indcGed":false}'));
        $ged = EmployeeAccountMapper::decode(JsonDecoder::decode('{"indcAma":false,"indcGed":true}'));

        static::assertTrue($ama->childminder());
        static::assertFalse($ama->homeCare());
        static::assertFalse($ged->childminder());
        static::assertTrue($ged->homeCare());
    }

    public function testAbsentFieldsTakeTheirDefaults(): void
    {
        foreach ([Payload::empty(), JsonDecoder::decode('{"numeroPajeSalarie":null,"indcAma":null,"indcGed":null,"etatCompteEnLigne":null}')] as $payload) {
            $account = EmployeeAccountMapper::decode($payload);

            static::assertNull($account->employeeNumber());
            static::assertFalse($account->childminder());
            static::assertFalse($account->homeCare());
            static::assertSame('', $account->state()->raw());
        }
    }

    public function testAnUndocumentedStateIsKeptRaw(): void
    {
        $account = EmployeeAccountMapper::decode(JsonDecoder::decode('{"etatCompteEnLigne":"Nouveau"}'));

        static::assertSame('Nouveau', $account->state()->raw());
        static::assertNull($account->state()->documented());
    }

    public function testAFieldOfTheWrongTypeIsRefused(): void
    {
        foreach ([
            '{"numeroPajeSalarie":1}' => 'field "numeroPajeSalarie" is not a string',
            '{"indcAma":"true"}' => 'field "indcAma" is not a boolean',
            '{"indcGed":1}' => 'field "indcGed" is not a boolean',
            '{"etatCompteEnLigne":true}' => 'field "etatCompteEnLigne" is not a string',
        ] as $json => $reason) {
            WireAssertions::assertUnexpected(
                'Unexpected URSSAF response: ' . $reason . '.',
                static fn(): mixed => EmployeeAccountMapper::decode(JsonDecoder::decode($json)),
            );
        }
    }
}
