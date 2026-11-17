<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationActivity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * The options shared by the console commands (--tenant, --limit, --dry-run) and their reading. The tenants, the
 * selectors, the prompts and the personal inputs have their own classes ({@see CommandTenants}, {@see CommandSelectors},
 * {@see CommandPrompts}, {@see CommandPersonalInputs}).
 *
 * @mago-expect lint:too-many-methods The options and their readers are one flat catalogue, split by the kind of value.
 */
final class CommandOptions
{
    private const POSITIVE = '/^[1-9][0-9]{0,8}$/D';
    private const NON_NEGATIVE = '/^(0|[1-9][0-9]{0,8})$/D';

    public static function addTenant(Command $command, string $description): void
    {
        $command->addOption('tenant', null, InputOption::VALUE_REQUIRED, $description);
    }

    public static function addLimit(Command $command): void
    {
        $command->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of records to process (capped by the workflow policy)');
    }

    public static function addDryRun(Command $command): void
    {
        $command->addOption('dry-run', null, InputOption::VALUE_NONE, 'List what would be done without calling URSSAF or changing anything');
    }

    public static function addActivity(Command $command): void
    {
        $command->addOption('activity', null, InputOption::VALUE_REQUIRED, 'Activity: "childminder" or "home_care" (required)');
    }

    /**
     * @throws InvalidOptionException when --activity is missing or unknown
     */
    public static function activity(InputInterface $input): DeclarationActivity
    {
        return DeclarationActivity::tryFrom(self::text($input, 'activity') ?? '') ?? throw new InvalidOptionException('The --activity option must be "childminder" or "home_care".');
    }

    public static function dryRun(InputInterface $input): bool
    {
        return $input->getOption('dry-run') === true;
    }

    public static function tenant(InputInterface $input): ?TenantId
    {
        $tenant = self::text($input, 'tenant');

        return $tenant === null ? null : TenantId::fromString($tenant);
    }

    /**
     * @throws InvalidOptionException for a value that is not a positive integer
     */
    public static function limit(InputInterface $input): ?int
    {
        $limit = self::text($input, 'limit');
        if ($limit === null) {
            return null;
        }

        return self::parse($limit, self::POSITIVE) ?? throw new InvalidOptionException('The --limit option must be a positive integer.');
    }

    /**
     * The value of an option, null when it is not given.
     */
    public static function text(InputInterface $input, string $name): ?string
    {
        // @mago-expect analysis:mixed-assignment Symfony options are untyped: the value is narrowed on the next line.
        $value = $input->getOption($name);

        return \is_string($value) ? $value : null;
    }

    /**
     * The value of an argument, null when it is not given.
     */
    public static function argument(InputInterface $input, string $name): ?string
    {
        // @mago-expect analysis:mixed-assignment Symfony arguments are untyped: the value is narrowed on the next line.
        $value = $input->getArgument($name);

        return \is_string($value) ? $value : null;
    }

    /**
     * The values of a repeatable option.
     *
     * @return list<string>
     */
    public static function texts(InputInterface $input, string $name): array
    {
        // @mago-expect analysis:mixed-assignment Symfony options are untyped: the values are narrowed below.
        $values = $input->getOption($name);

        $texts = [];
        // @mago-expect analysis:mixed-assignment The values of the untyped option are narrowed to strings in the loop.
        foreach (\is_array($values) ? $values : [] as $value) {
            if (!\is_string($value)) {
                continue;
            }

            $texts[] = $value;
        }

        return $texts;
    }

    /**
     * A non-negative integer option, null when it is not given.
     *
     * @throws InvalidOptionException for a value that is not a non-negative integer
     */
    public static function integer(InputInterface $input, string $name): ?int
    {
        $text = self::text($input, $name);
        if ($text === null) {
            return null;
        }

        return self::parse($text, self::NON_NEGATIVE) ?? throw new InvalidOptionException(\sprintf('The --%s option must be a non-negative integer.', $name));
    }

    /**
     * No filter_var: the bridge does not require ext-filter.
     *
     * @param non-empty-string $pattern
     */
    private static function parse(string $value, string $pattern): ?int
    {
        return preg_match($pattern, $value) === 1 ? (int) $value : null;
    }
}
