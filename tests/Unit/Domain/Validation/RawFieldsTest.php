<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Validation;

use Closure;
use PHPUnit\Framework\TestCase;
use stdClass;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\RawFields;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\Violation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

/**
 * @mago-expect lint:too-many-methods
 */
final class RawFieldsTest extends TestCase
{
    /**
     * Runs the reader on the input and returns what it read with the violations, as "<severity> <path> <code>".
     *
     * @param array<array-key, mixed>   $input
     * @param Closure(RawFields): mixed $read
     *
     * @param-immediately-invoked-callable $read
     *
     * @return array{mixed, list<string>}
     */
    private static function read(array $input, Closure $read): array
    {
        $value = null;
        $result = RawFields::validate($input, static function (RawFields $fields) use ($read, &$value): ?stdClass {
            // @mago-expect analysis:mixed-assignment The reader returns whatever the tested method read.
            $value = $read($fields);

            return null;
        });

        return [$value, DomainAssertions::summary($result)];
    }


    private static function requiredDate(RawFields $fields): ?object
    {
        return $fields->required('birth_date', CalendarDate::fromString(...));
    }

    private static function optionalDate(RawFields $fields): ?object
    {
        return $fields->optional('birth_date', CalendarDate::fromString(...));
    }

    private static function nestedDate(RawFields $fields): ?object
    {
        return $fields->nested('child', self::requiredDate(...));
    }

    private static function optionalNestedDate(RawFields $fields): ?object
    {
        return $fields->optionalNested('child', self::requiredDate(...));
    }

    /**
     * @return list<object|null>
     */
    private static function dateItems(RawFields $fields): array
    {
        return $fields->items('children', self::requiredDate(...));
    }

    private static function buildInvalid(RawFields $fields): null
    {
        return $fields->build('some_path', static fn(): object => throw InvalidValue::empty('thing'));
    }

    public function testTextReadsStringsAndIntegers(): void
    {
        static::assertSame(['abc', []], self::read(['k' => 'abc'], static fn(RawFields $f): ?string => $f->text('k')));
        static::assertSame(['12', []], self::read(['k' => 12], static fn(RawFields $f): ?string => $f->text('k')));
        static::assertSame(['0', []], self::read(['k' => 0], static fn(RawFields $f): ?string => $f->text('k')));
    }

    public function testTextTreatsNullEmptyAndAbsentAsMissing(): void
    {
        static::assertSame([null, []], self::read(['k' => null], static fn(RawFields $f): ?string => $f->text('k')));
        static::assertSame([null, []], self::read(['k' => ''], static fn(RawFields $f): ?string => $f->text('k')));
        static::assertSame([null, []], self::read([], static fn(RawFields $f): ?string => $f->text('k')));
    }

    public function testTextReportsAnyOtherType(): void
    {
        foreach ([1.5, true, false, ['a'], new stdClass()] as $value) {
            static::assertSame(
                [null, ['error some_key some_key.invalid_type']],
                self::read(['some_key' => $value], static fn(RawFields $f): ?string => $f->text('some_key')),
            );
        }
    }

    public function testIntegerReadsIntegersAndDigitStrings(): void
    {
        static::assertSame([5, []], self::read(['k' => 5], static fn(RawFields $f): ?int => $f->integer('k')));
        static::assertSame([0, []], self::read(['k' => 0], static fn(RawFields $f): ?int => $f->integer('k')));
        static::assertSame([42, []], self::read(['k' => '42'], static fn(RawFields $f): ?int => $f->integer('k')));
        static::assertSame([-3, []], self::read(['k' => '-3'], static fn(RawFields $f): ?int => $f->integer('k')));
        static::assertSame([null, []], self::read(['k' => null], static fn(RawFields $f): ?int => $f->integer('k')));
        static::assertSame([null, []], self::read(['k' => ''], static fn(RawFields $f): ?int => $f->integer('k')));
        static::assertSame([null, []], self::read([], static fn(RawFields $f): ?int => $f->integer('k')));
    }

    public function testIntegerReportsAnyOtherInput(): void
    {
        foreach (['12a', '1.5', '99999999999999999999', 1.5, true, ['1']] as $value) {
            static::assertSame(
                [null, ['error count count.invalid_type']],
                self::read(['count' => $value], static fn(RawFields $f): ?int => $f->integer('count')),
            );
        }
    }

    public function testBooleanReadsBooleansAndTheirStrings(): void
    {
        foreach ([true, '1', 'true'] as $value) {
            static::assertSame([true, []], self::read(['k' => $value], static fn(RawFields $f): ?bool => $f->boolean('k')));
        }
        foreach ([false, '0', 'false'] as $value) {
            static::assertSame([false, []], self::read(['k' => $value], static fn(RawFields $f): ?bool => $f->boolean('k')));
        }
        static::assertSame([null, []], self::read(['k' => null], static fn(RawFields $f): ?bool => $f->boolean('k')));
        static::assertSame([null, []], self::read(['k' => ''], static fn(RawFields $f): ?bool => $f->boolean('k')));
        static::assertSame([null, []], self::read([], static fn(RawFields $f): ?bool => $f->boolean('k')));
    }

    public function testBooleanReportsAnyOtherInput(): void
    {
        foreach (['yes', 'TRUE', 1, 0, 1.0, ['true']] as $value) {
            static::assertSame(
                [null, ['error flag flag.invalid_type']],
                self::read(['flag' => $value], static fn(RawFields $f): ?bool => $f->boolean('flag')),
            );
        }
    }

    public function testRequiredReadersReportAMissingField(): void
    {
        $text = static fn(RawFields $f): ?string => $f->requiredText('the_text');
        $integer = static fn(RawFields $f): ?int => $f->requiredInteger('the_count');
        $boolean = static fn(RawFields $f): ?bool => $f->requiredBoolean('the_flag');

        static::assertSame([null, ['error the_text the_text.required']], self::read([], $text));
        static::assertSame([null, ['error the_text the_text.required']], self::read(['the_text' => ''], $text));
        static::assertSame([null, ['error the_count the_count.required']], self::read(['the_count' => null], $integer));
        static::assertSame([null, ['error the_flag the_flag.required']], self::read([], $boolean));
        static::assertSame(['x', []], self::read(['the_text' => 'x'], $text));
        static::assertSame([0, []], self::read(['the_count' => 0], $integer));
        static::assertSame([false, []], self::read(['the_flag' => false], $boolean));
    }

    public function testRequiredReadersDoNotReportRequiredOnTopOfAnInvalidType(): void
    {
        static::assertSame(
            [null, ['error the_text the_text.invalid_type']],
            self::read(['the_text' => 1.5], static fn(RawFields $f): ?string => $f->requiredText('the_text')),
        );
        static::assertSame(
            [null, ['error the_count the_count.invalid_type']],
            self::read(['the_count' => 'x'], static fn(RawFields $f): ?int => $f->requiredInteger('the_count')),
        );
    }

    public function testRequiredAndOptionalParseTheText(): void
    {
        $required = self::requiredDate(...);
        $optional = self::optionalDate(...);

        // @mago-expect analysis:mixed-assignment The reader returns whatever the tested method read.
        $date = self::read(['birth_date' => '2020-01-02'], $required)[0];
        static::assertInstanceOf(CalendarDate::class, $date);
        static::assertSame('2020-01-02', $date->toString());
        static::assertSame([null, ['error birth_date birth_date.required']], self::read([], $required));
        static::assertSame([null, ['error birth_date calendar_date.invalid_format']], self::read(['birth_date' => 'x'], $required));
        static::assertSame([null, []], self::read([], $optional));
        static::assertSame([null, []], self::read(['birth_date' => ''], $optional));
        static::assertSame([null, ['error birth_date calendar_date.invalid_date']], self::read(['birth_date' => '2020-02-31'], $optional));
        static::assertSame([null, ['error birth_date birth_date.invalid_type']], self::read(['birth_date' => []], $required));
        static::assertSame([null, ['error birth_date birth_date.invalid_type']], self::read(['birth_date' => []], $optional));
        // @mago-expect analysis:mixed-assignment The reader returns whatever the tested method read.
        $parsed = self::read(['birth_date' => '2020-01-02'], $optional)[0];
        static::assertInstanceOf(CalendarDate::class, $parsed);
    }

    public function testNestedParsesAMissingMapAsEmptyAndNestsTheViolations(): void
    {
        $nested = self::nestedDate(...);

        static::assertSame([null, ['error child.birth_date birth_date.required']], self::read([], $nested));
        static::assertSame([null, ['error child.birth_date calendar_date.invalid_format']], self::read(['child' => ['birth_date' => 'x']], $nested));
        static::assertInstanceOf(CalendarDate::class, self::read(['child' => ['birth_date' => '2020-01-02']], $nested)[0]);
        static::assertSame([null, ['error child child.invalid_type']], self::read(['child' => 'x'], $nested));
    }

    public function testOptionalNestedSkipsAMissingMap(): void
    {
        $nested = self::optionalNestedDate(...);

        static::assertSame([null, []], self::read([], $nested));
        static::assertSame([null, []], self::read(['child' => null], $nested));
        static::assertSame([null, ['error child.birth_date birth_date.required']], self::read(['child' => []], $nested));
        static::assertSame([null, ['error child child.invalid_type']], self::read(['child' => 'x'], $nested));
        static::assertInstanceOf(CalendarDate::class, self::read(['child' => ['birth_date' => '2020-01-02']], $nested)[0]);
    }

    public function testItemsParsesEveryItemAndIndexesTheViolations(): void
    {
        // @mago-expect analysis:mixed-assignment The reader returns whatever the tested method read.
        [$parsed, $violations] = self::read(
            ['children' => ['a' => ['birth_date' => '2020-01-02'], 'b' => [], 'c' => 'x', 'd' => ['birth_date' => 'y']]],
            self::dateItems(...),
        );

        static::assertIsArray($parsed);
        static::assertCount(4, $parsed);
        static::assertInstanceOf(CalendarDate::class, $parsed[0]);
        static::assertNull($parsed[1]);
        static::assertNull($parsed[2]);
        static::assertNull($parsed[3]);
        static::assertSame(
            [
                'error children[1].birth_date birth_date.required',
                'error children[2] children.invalid_type',
                'error children[3].birth_date calendar_date.invalid_format',
            ],
            $violations,
        );
    }

    public function testItemsOfAMissingOrInvalidList(): void
    {
        $items = static fn(RawFields $f): array => $f->items('children', static fn(): ?object => null);

        static::assertSame([[], []], self::read([], $items));
        static::assertSame([[], []], self::read(['children' => []], $items));
        static::assertSame([[null], ['error children children.invalid_type']], self::read(['children' => 'x'], $items));
    }

    public function testBuildReportsAnInvalidValueAtThePath(): void
    {
        static::assertSame([null, ['error some_path thing.empty']], self::read([], self::buildInvalid(...)));
        static::assertInstanceOf(stdClass::class, self::read([], static fn(RawFields $f): ?object => $f->build('p', static fn(): stdClass => new stdClass()))[0]);
    }

    public function testBuildTranslatesTheCamelCasePathsOfAnAggregate(): void
    {
        $result = ValidationResult::of(
            Violation::error('hours', 'hours.out_of_range', 'm'),
            Violation::error('netMonthlySalary', 'net_monthly_salary.not_positive', 'm'),
            Violation::error('overtimeHours25', 'overtime_hours_25.out_of_range', 'm'),
            Violation::error('consecutive24hDays', 'consecutive_24h_days.out_of_range', 'm'),
            Violation::error('', 'children.empty', 'm'),
            Violation::error('lines[2].unitPrice', 'a.b', 'm'),
            Violation::warning('birthCity', 'birth_city.warning', 'w', ['k' => 1]),
        );
        $validation = RawFields::validate([], static function (RawFields $f) use ($result): ?stdClass {
            $f->build('common', static fn(): object => throw ValidationFailed::withResult($result));

            return null;
        });

        static::assertSame(
            [
                'error common.hours hours.out_of_range',
                'error common.net_monthly_salary net_monthly_salary.not_positive',
                'error common.overtime_hours_25 overtime_hours_25.out_of_range',
                'error common.consecutive_24h_days consecutive_24h_days.out_of_range',
                'error common children.empty',
                'error common.lines[2].unit_price a.b',
                'warning common.birth_city birth_city.warning',
            ],
            DomainAssertions::summary($validation),
        );
        $warning = $validation->warnings()[0];
        static::assertSame('w', $warning->message());
        static::assertSame(['k' => 1], $warning->params());
        static::assertSame('m', $validation->errors()[0]->message());
    }

    public function testAddAndErrorCount(): void
    {
        $counts = [];
        $result = RawFields::validate([], static function (RawFields $f) use (&$counts): ?stdClass {
            $counts[] = $f->errorCount();
            $f->add(Violation::warning('a', 'a.b', 'w'));
            $counts[] = $f->errorCount();
            $f->add(Violation::error('a', 'a.c', 'e'));
            $counts[] = $f->errorCount();

            return null;
        });

        static::assertSame([0, 0, 1], $counts);
        static::assertSame(['warning a a.b', 'error a a.c'], DomainAssertions::summary($result));
    }

    public function testResolveReturnsTheBuiltValueOrThrowsEveryViolation(): void
    {
        static::assertSame('ok', RawFields::resolve([], static fn(): string => 'ok'));

        $violations = DomainAssertions::assertViolationCode('validation.failed', static function (): void {
            RawFields::resolve([], static function (RawFields $f): ?stdClass {
                $f->requiredText('a');
                $f->requiredText('b');

                return null;
            });
        });
        static::assertInstanceOf(ValidationFailed::class, $violations);
        static::assertSame(['error a a.required', 'error b b.required'], DomainAssertions::summary($violations->result()));
    }

    public function testMessagesAreReadableAndFreeOfRawValues(): void
    {
        $result = RawFields::validate(['some_key' => 'SECRET'], static function (RawFields $f): ?stdClass {
            $f->requiredText('the_text');
            $f->boolean('some_key');

            return null;
        });

        static::assertSame('The the text is required.', $result->errors()[0]->message());
        static::assertSame('The some key has an unsupported type.', $result->errors()[1]->message());
    }

    public function testRawInputIsSensitive(): void
    {
        DomainAssertions::assertSensitiveParameters(RawFields::class, 'validate', ['input']);
        DomainAssertions::assertSensitiveParameters(RawFields::class, 'resolve', ['input']);
        DomainAssertions::assertSensitiveParameters(RawFields::class, '__construct', ['values']);
    }

    public function testNoRawValueAppearsInAViolation(): void
    {
        [, $violations] = self::read(['name' => 'SECRET-VALUE', 'n' => 'SECRET-N'], static function (RawFields $f): void {
            $f->integer('n');
            $f->boolean('name');
        });
        $result = RawFields::validate(['name' => 'SECRET-VALUE'], static function (RawFields $f): ?stdClass {
            $f->boolean('name');

            return null;
        });

        static::assertStringNotContainsString('SECRET', implode(' ', $violations));
        static::assertStringNotContainsString('SECRET', $result->errors()[0]->message());
    }
}
