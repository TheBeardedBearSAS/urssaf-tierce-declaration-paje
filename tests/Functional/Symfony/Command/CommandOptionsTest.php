<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\CommandOptions;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationActivity;

/**
 * @mago-expect lint:too-many-methods
 */
final class CommandOptionsTest extends TestCase
{
    /**
     * @param array<string, mixed> $values
     */
    private static function input(array $values): ArrayInput
    {
        $command = new Command('probe');
        CommandOptions::addTenant($command, 'Tenant');
        CommandOptions::addLimit($command);
        CommandOptions::addDryRun($command);
        CommandOptions::addActivity($command);
        $command->addOption('count', null, InputOption::VALUE_REQUIRED);
        $command->addOption('many', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY);
        $command->addArgument('name', InputArgument::OPTIONAL);

        return new ArrayInput($values, $command->getDefinition());
    }

    public function testTheOptionsAreDeclared(): void
    {
        $definition = self::input([])->getFirstArgument();
        $command = new Command('probe');
        CommandOptions::addTenant($command, 'Tenant of the run');
        CommandOptions::addLimit($command);
        CommandOptions::addDryRun($command);
        CommandOptions::addActivity($command);

        static::assertNull($definition);
        static::assertSame('Tenant of the run', $command->getDefinition()->getOption('tenant')->getDescription());
        static::assertTrue($command->getDefinition()->getOption('tenant')->isValueRequired());
        static::assertTrue($command->getDefinition()->getOption('limit')->isValueRequired());
        static::assertFalse($command->getDefinition()->getOption('dry-run')->acceptValue());
        static::assertTrue($command->getDefinition()->getOption('activity')->isValueRequired());
    }

    public function testTheTenantIsReadWhenGiven(): void
    {
        static::assertNull(CommandOptions::tenant(self::input([])));
        static::assertSame('tenant-1', CommandOptions::tenant(self::input(['--tenant' => 'tenant-1']))?->value());
    }

    public function testTheDryRunIsOnlyOnWhenGiven(): void
    {
        static::assertFalse(CommandOptions::dryRun(self::input([])));
        static::assertTrue(CommandOptions::dryRun(self::input(['--dry-run' => true])));
    }

    public function testTextAndArgumentAreNullWhenNotGiven(): void
    {
        static::assertNull(CommandOptions::text(self::input([]), 'count'));
        static::assertSame('5', CommandOptions::text(self::input(['--count' => '5']), 'count'));
        static::assertNull(CommandOptions::argument(self::input([]), 'name'));
        static::assertSame('x', CommandOptions::argument(self::input(['name' => 'x']), 'name'));
    }

    public function testTheValuesOfARepeatableOptionAreListed(): void
    {
        static::assertSame([], CommandOptions::texts(self::input([]), 'many'));
        static::assertSame(['a', 'b'], CommandOptions::texts(self::input(['--many' => ['a', 'b']]), 'many'));
        static::assertSame([], CommandOptions::texts(self::input(['--count' => '5']), 'count'));
        static::assertSame(['a', 'c'], CommandOptions::texts(self::input(['--many' => ['a', 5, 'c']]), 'many'));
    }

    #[DataProvider('limits')]
    public function testALimitIsAPositiveInteger(?string $value, ?int $expected): void
    {
        static::assertSame($expected, CommandOptions::limit(self::input($value === null ? [] : ['--limit' => $value])));
    }

    /**
     * @return iterable<string, array{?string, ?int}>
     */
    public static function limits(): iterable
    {
        yield 'absent' => [null, null];
        yield 'one' => ['1', 1];
        yield 'many digits' => ['123456789', 123_456_789];
    }

    #[DataProvider('invalidLimits')]
    public function testAnInvalidLimitIsRefused(string $value): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The --limit option must be a positive integer.');

        CommandOptions::limit(self::input(['--limit' => $value]));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLimits(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-1'];
        yield 'text' => ['ten'];
        yield 'decimal' => ['1.5'];
        yield 'leading zero' => ['01'];
        yield 'too long' => ['1234567890'];
        yield 'trailing newline' => ["1\n"];
        yield 'empty' => [''];
        yield 'prefixed' => ['x1'];
    }

    public function testANonNegativeIntegerIsRead(): void
    {
        static::assertNull(CommandOptions::integer(self::input([]), 'count'));
        static::assertSame(0, CommandOptions::integer(self::input(['--count' => '0']), 'count'));
        static::assertSame(12, CommandOptions::integer(self::input(['--count' => '12']), 'count'));
        static::assertSame(123_456_789, CommandOptions::integer(self::input(['--count' => '123456789']), 'count'));
    }

    #[DataProvider('invalidIntegers')]
    public function testAnInvalidIntegerIsRefused(string $value): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The --count option must be a non-negative integer.');

        CommandOptions::integer(self::input(['--count' => $value]), 'count');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIntegers(): iterable
    {
        yield 'negative' => ['-1'];
        yield 'text' => ['x'];
        yield 'leading zero' => ['01'];
        yield 'too long' => ['1234567890'];
        yield 'trailing newline' => ["1\n"];
        yield 'prefixed' => ['x1'];
    }

    public function testTheActivityIsChosenByName(): void
    {
        static::assertSame(DeclarationActivity::Childminder, CommandOptions::activity(self::input(['--activity' => 'childminder'])));
        static::assertSame(DeclarationActivity::HomeCare, CommandOptions::activity(self::input(['--activity' => 'home_care'])));
    }

    #[DataProvider('invalidActivities')]
    public function testAnUnknownActivityIsRefused(?string $value): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The --activity option must be "childminder" or "home_care".');

        CommandOptions::activity(self::input($value === null ? [] : ['--activity' => $value]));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function invalidActivities(): iterable
    {
        yield 'absent' => [null];
        yield 'unknown' => ['nanny'];
        yield 'home care with a dash' => ['home-care'];
    }
}
