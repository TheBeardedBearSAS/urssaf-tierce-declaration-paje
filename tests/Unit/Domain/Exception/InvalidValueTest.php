<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Exception;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\Severity;

final class InvalidValueTest extends TestCase
{
    /**
     * @return iterable<string, array{Closure(): InvalidValue, string, string, array<string, scalar>}>
     */
    public static function provideNamedConstructors(): iterable
    {
        yield 'because' => [
            static fn(): InvalidValue => InvalidValue::because('foo.bar', 'Custom message.', ['limit' => 3]),
            'foo.bar',
            'Custom message.',
            ['limit' => 3],
        ];
        yield 'because without params' => [static fn(): InvalidValue => InvalidValue::because('foo.bar', 'Custom message.'), 'foo.bar', 'Custom message.', []];
        yield 'empty' => [static fn(): InvalidValue => InvalidValue::empty('tenant_id'), 'tenant_id.empty', 'Invalid tenant_id: the value must not be empty.', []];
        yield 'too long' => [
            static fn(): InvalidValue => InvalidValue::tooLong('tenant_id', 64),
            'tenant_id.too_long',
            'Invalid tenant_id: the value must not exceed 64 characters.',
            ['max_length' => 64],
        ];
        yield 'too short' => [
            static fn(): InvalidValue => InvalidValue::tooShort('postal_code', 5),
            'postal_code.too_short',
            'Invalid postal_code: the value must have at least 5 characters.',
            ['min_length' => 5],
        ];
        yield 'invalid format' => [
            static fn(): InvalidValue => InvalidValue::invalidFormat('paje_number', '14 alphanumeric characters'),
            'paje_number.invalid_format',
            'Invalid paje_number: the value does not match the expected format (14 alphanumeric characters).',
            ['expected_format' => '14 alphanumeric characters'],
        ];
        yield 'negative' => [static fn(): InvalidValue => InvalidValue::negative('money'), 'money.negative', 'Invalid money: the value must not be negative.', []];
        yield 'not positive' => [
            static fn(): InvalidValue => InvalidValue::notPositive('deposit'),
            'deposit.not_positive',
            'Invalid deposit: the value must be strictly positive.',
            [],
        ];
        yield 'too many decimals' => [
            static fn(): InvalidValue => InvalidValue::tooManyDecimals('money', 2),
            'money.too_many_decimals',
            'Invalid money: the value must not have more than 2 decimals.',
            ['max_decimals' => 2],
        ];
        yield 'too large' => [
            static fn(): InvalidValue => InvalidValue::tooLarge('money'),
            'money.too_large',
            'Invalid money: the value exceeds the supported range.',
            [],
        ];
        yield 'out of range with integers' => [
            static fn(): InvalidValue => InvalidValue::outOfRange('street_number', 1, 9999),
            'street_number.out_of_range',
            'Invalid street_number: the value must be between 1 and 9999.',
            ['min' => 1, 'max' => 9999],
        ];
        yield 'out of range with decimal strings' => [
            static fn(): InvalidValue => InvalidValue::outOfRange('money', '0.00', '999.99'),
            'money.out_of_range',
            'Invalid money: the value must be between 0.00 and 999.99.',
            ['min' => '0.00', 'max' => '999.99'],
        ];
        yield 'unknown' => [
            static fn(): InvalidValue => InvalidValue::unknown('civility'),
            'civility.unknown',
            'Invalid civility: the value is not one of the accepted codes.',
            [],
        ];
        yield 'invalid characters' => [
            static fn(): InvalidValue => InvalidValue::invalidCharacters('last_name'),
            'last_name.invalid_characters',
            'Invalid last_name: the value contains characters that are not allowed.',
            [],
        ];
        yield 'not multiple of' => [
            static fn(): InvalidValue => InvalidValue::notMultipleOf('half_days', '0.5'),
            'half_days.not_multiple_of',
            'Invalid half_days: the value must be a multiple of 0.5.',
            ['step' => '0.5'],
        ];
    }

    /**
     * @param Closure(): InvalidValue $factory
     * @param array<string, scalar>   $params
     */
    #[DataProvider('provideNamedConstructors')]
    public function testNamedConstructors(Closure $factory, string $code, string $message, array $params): void
    {
        $exception = $factory();

        static::assertSame($code, $exception->violationCode());
        static::assertSame($message, $exception->getMessage());
        static::assertSame($params, $exception->params());
        static::assertSame(0, $exception->getCode());
        static::assertNull($exception->getPrevious());
    }

    public function testItConvertsToAnErrorViolation(): void
    {
        $violation = InvalidValue::tooLong('declaration_id', 50)->toViolation('lines[1].declarationId');

        static::assertSame('lines[1].declarationId', $violation->path());
        static::assertSame('declaration_id.too_long', $violation->code());
        static::assertSame('Invalid declaration_id: the value must not exceed 50 characters.', $violation->message());
        static::assertSame(Severity::Error, $violation->severity());
        static::assertSame(['max_length' => 50], $violation->params());
    }
}
