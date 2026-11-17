<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Port;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\OperationRetryClass;

final class OperationTest extends TestCase
{
    /**
     * @return iterable<string, array{Operation, string, OperationRetryClass, string}>
     */
    public static function provideOperations(): iterable
    {
        yield 'verify employer' => [Operation::VerifyEmployer, '020', OperationRetryClass::Safe, 'verify employer'];
        yield 'verify employee' => [Operation::VerifyEmployee, '021', OperationRetryClass::Safe, 'verify employee'];
        yield 'verify children' => [Operation::VerifyChildren, '022', OperationRetryClass::Safe, 'verify children'];
        yield 'associate employee' => [
            Operation::AssociateEmployee,
            '023',
            OperationRetryClass::UnspecifiedWrite,
            'associate employee',
        ];
        yield 'register mandate' => [
            Operation::RegisterMandate,
            '030',
            OperationRetryClass::UnspecifiedWrite,
            'register mandate',
        ];
        yield 'cancel mandate' => [
            Operation::CancelMandate,
            '031',
            OperationRetryClass::UnspecifiedWrite,
            'cancel mandate',
        ];
        yield 'estimate childminder' => [
            Operation::EstimateChildminder,
            '040',
            OperationRetryClass::Safe,
            'estimate childminder contributions',
        ];
        yield 'estimate home care' => [
            Operation::EstimateHomeCare,
            '041',
            OperationRetryClass::Safe,
            'estimate home care contributions',
        ];
        yield 'pre-declare childminder' => [
            Operation::PreDeclareChildminder,
            '045',
            OperationRetryClass::Safe,
            'pre-declare childminder salary',
        ];
        yield 'pre-declare home care' => [
            Operation::PreDeclareHomeCare,
            '046',
            OperationRetryClass::Safe,
            'pre-declare home care salary',
        ];
        yield 'declare childminder' => [
            Operation::DeclareChildminder,
            '050',
            OperationRetryClass::NeverResent,
            'declare childminder salary',
        ];
        yield 'declare home care' => [
            Operation::DeclareHomeCare,
            '051',
            OperationRetryClass::NeverResent,
            'declare home care salary',
        ];
    }

    #[DataProvider('provideOperations')]
    public function testEveryOperation(
        Operation $operation,
        string $number,
        OperationRetryClass $retryClass,
        string $label,
    ): void {
        static::assertSame($number, $operation->value);
        static::assertSame($retryClass, $operation->retryClass());
        static::assertSame($label, $operation->label());
    }

    public function testThereAreTwelveOperations(): void
    {
        static::assertCount(12, Operation::cases());
    }

    public function testRetryClassValues(): void
    {
        static::assertSame(
            ['safe', 'never_resent', 'unspecified_write'],
            array_map(static fn(OperationRetryClass $class): string => $class->value, OperationRetryClass::cases()),
        );
    }
}
