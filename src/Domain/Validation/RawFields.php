<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation;

use Closure;
use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;

/**
 * Reads the raw fields of one host input map (what a host application, a JSON body or a console command gives) and
 * reports every violation instead of failing fast. Used by the factories of the `Input` namespaces only.
 *
 * Conventions (ADR 0000 item 4):
 * - keys and violation paths are English snake_case ("net_monthly_salary", "children[0].last_name"); the camelCase
 *   paths of the aggregates are translated to the key the host typed ({@see self::build()});
 * - a field that is null or "" is missing; text accepts a string or an integer (console and form input are strings),
 *   integers accept an integer or a digit string, booleans accept a boolean or "1", "0", "true", "false"; decimals
 *   are text (a float is refused, ADR 0000 item 3);
 * - any other type reports "<key>.invalid_type" and never throws a TypeError;
 * - no raw value is ever put in a violation (ADR 0007).
 *
 * One reader per kind of raw field, hence the method count.
 *
 * @internal
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 */
final readonly class RawFields
{
    /**
     * @param array<array-key, mixed> $values
     */
    private function __construct(
        #[SensitiveParameter]
        private array $values,
        private ViolationCollector $collector,
    ) {}

    /**
     * Runs the parser and returns the violations of the input (warnings included).
     *
     * @param array<array-key, mixed> $input
     * @param Closure(self): mixed    $parse
     *
     * @param-immediately-invoked-callable $parse
     */
    public static function validate(#[SensitiveParameter] array $input, Closure $parse): ValidationResult
    {
        $collector = new ViolationCollector();
        $parse(new self($input, $collector));

        return $collector->result();
    }

    /**
     * Runs the parser and returns what it built.
     *
     * @template T
     *
     * @param array<array-key, mixed> $input
     * @param Closure(self): (T|null) $parse
     *
     * @param-immediately-invoked-callable $parse
     *
     * @return T
     *
     * @throws ValidationFailed with every violation
     */
    public static function resolve(#[SensitiveParameter] array $input, Closure $parse): mixed
    {
        $collector = new ViolationCollector();

        return $parse(new self($input, $collector)) ?? throw ValidationFailed::withResult($collector->result());
    }

    /**
     * The text of the field: a string or an integer, null when missing or of another type (reported).
     */
    public function text(string $key): ?string
    {
        // @mago-expect analysis:mixed-assignment Raw host input, narrowed right below.
        $value = $this->values[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (\is_string($value)) {
            return $value;
        }
        if (\is_int($value)) {
            return (string) $value;
        }

        return $this->invalidType($key);
    }

    /**
     * The integer of the field: an integer or a digit string, null when missing or of another type (reported).
     */
    public function integer(string $key): ?int
    {
        // @mago-expect analysis:mixed-assignment Raw host input, narrowed right below.
        $value = $this->values[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (\is_int($value)) {
            return $value;
        }
        $parsed = \is_string($value) ? filter_var($value, FILTER_VALIDATE_INT) : false;
        if ($parsed === false) {
            return $this->invalidType($key);
        }

        return $parsed;
    }

    /**
     * The boolean of the field: a boolean or "1", "0", "true", "false", null when missing or of another type
     * (reported).
     */
    public function boolean(string $key): ?bool
    {
        // @mago-expect analysis:mixed-assignment Raw host input, narrowed right below.
        $value = $this->values[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (\is_bool($value)) {
            return $value;
        }
        if ($value === '1' || $value === 'true') {
            return true;
        }
        if ($value === '0' || $value === 'false') {
            return false;
        }

        return $this->invalidType($key);
    }

    /**
     * Reports "<key>.required" when the text is missing.
     */
    public function requiredText(string $key): ?string
    {
        return $this->requiredValue($key, fn(): ?string => $this->text($key));
    }

    /**
     * Reports "<key>.required" when the integer is missing. Its range is the business of the aggregate.
     */
    public function requiredInteger(string $key): ?int
    {
        return $this->requiredValue($key, fn(): ?int => $this->integer($key));
    }

    /**
     * Reports "<key>.required" when the boolean is missing.
     */
    public function requiredBoolean(string $key): ?bool
    {
        return $this->requiredValue($key, fn(): ?bool => $this->boolean($key));
    }

    /**
     * Parses the text into a value object, reports "<key>.required" when it is missing and the exception of the
     * factory when it is invalid.
     *
     * @template T of object
     *
     * @param Closure(string): T $factory
     *
     * @param-immediately-invoked-callable $factory
     *
     * @return T|null null when missing or invalid
     */
    public function required(string $key, Closure $factory): ?object
    {
        $raw = $this->requiredText($key);

        return $raw === null ? null : $this->collector->attempt($key, static fn(): object => $factory($raw));
    }

    /**
     * @template T of object
     *
     * @param Closure(string): T $factory
     *
     * @param-immediately-invoked-callable $factory
     *
     * @return T|null null when missing or invalid
     */
    public function optional(string $key, Closure $factory): ?object
    {
        $raw = $this->text($key);

        return $raw === null ? null : $this->collector->attempt($key, static fn(): object => $factory($raw));
    }

    /**
     * Parses a nested map; a missing map is parsed as a map without any field (so that its required fields are
     * reported). The violations are nested under the key.
     *
     * @template T of object
     *
     * @param Closure(self): (T|null) $parse
     *
     * @param-immediately-invoked-callable $parse
     *
     * @return T|null null when invalid
     */
    public function nested(string $key, Closure $parse): ?object
    {
        return $this->parseMap($key, $key, $this->values[$key] ?? [], $parse);
    }

    /**
     * Like {@see self::nested()}, but a missing map is not parsed at all.
     *
     * @template T of object
     *
     * @param Closure(self): (T|null) $parse
     *
     * @param-immediately-invoked-callable $parse
     *
     * @return T|null null when missing or invalid
     */
    public function optionalNested(string $key, Closure $parse): ?object
    {
        // @mago-expect analysis:mixed-assignment Raw host input, narrowed right below.
        $raw = $this->values[$key] ?? null;

        return $raw === null ? null : $this->parseMap($key, $key, $raw, $parse);
    }

    /**
     * Parses a list of maps, one result per item in order (null for an invalid item), the violations nested under
     * "<key>[i]". A missing list is empty; a list of another type counts as one invalid item.
     *
     * @template T of object
     *
     * @param Closure(self): (T|null) $parse
     *
     * @param-immediately-invoked-callable $parse
     *
     * @return list<T|null>
     */
    public function items(string $key, Closure $parse): array
    {
        // @mago-expect analysis:mixed-assignment Raw host input, narrowed right below.
        $raw = $this->values[$key] ?? [];
        if (!\is_array($raw)) {
            $this->invalidType($key);

            return [null];
        }
        $items = [];
        // @mago-expect analysis:mixed-assignment Raw host input, narrowed right below.
        foreach (array_values($raw) as $index => $item) {
            $items[] = $this->parseMap($key . '[' . $index . ']', $key, $item, $parse);
        }

        return $items;
    }

    /**
     * Builds an aggregate and reports its violations: an {@see InvalidValue} at the path, the result of a
     * {@see ValidationFailed} nested under it with its camelCase paths translated to the snake_case keys of the input.
     *
     * @template T of object
     *
     * @param Closure(): T $factory
     *
     * @param-immediately-invoked-callable $factory
     *
     * @return T|null null when invalid
     */
    public function build(string $path, Closure $factory): ?object
    {
        try {
            return $factory();
        } catch (InvalidValue $invalidValue) {
            $this->collector->add($invalidValue->toViolation($path));
        } catch (ValidationFailed $validationFailed) {
            $this->collector->merge(self::translated($validationFailed->result()), $path);
        }

        return null;
    }

    public function add(Violation $violation): void
    {
        $this->collector->add($violation);
    }

    public function errorCount(): int
    {
        return $this->collector->errorCount();
    }

    /**
     * @template T
     *
     * @param Closure(): T $read
     *
     * @param-immediately-invoked-callable $read
     *
     * @return T|null
     */
    private function requiredValue(string $key, Closure $read): mixed
    {
        $errorsBefore = $this->collector->errorCount();
        $value = $read();
        if ($value === null && $this->collector->errorCount() === $errorsBefore) {
            $this->collector->add(Violation::error(
                $key,
                $key . '.required',
                \sprintf('The %s is required.', str_replace('_', ' ', $key)),
            ));
        }

        return $value;
    }

    /**
     * @template T of object
     *
     * @param Closure(self): (T|null) $parse
     *
     * @param-immediately-invoked-callable $parse
     *
     * @return T|null
     */
    private function parseMap(string $path, string $subject, mixed $raw, Closure $parse): ?object
    {
        if (!\is_array($raw)) {
            $this->collector->add(self::invalidTypeViolation($path, $subject));

            return null;
        }
        $nested = new ViolationCollector();
        $value = $parse(new self($raw, $nested));
        $this->collector->merge($nested->result(), $path);

        return $value;
    }

    private function invalidType(string $key): null
    {
        $this->collector->add(self::invalidTypeViolation($key, $key));

        return null;
    }

    private static function invalidTypeViolation(string $path, string $subject): Violation
    {
        return Violation::error(
            $path,
            $subject . '.invalid_type',
            \sprintf('The %s has an unsupported type.', str_replace('_', ' ', $subject)),
        );
    }

    private static function translated(ValidationResult $result): ValidationResult
    {
        return ValidationResult::of(...array_map(
            static function (Violation $violation): Violation {
                $path = strtolower((string) preg_replace('/(?<=[a-z])(?=[A-Z0-9])|(?<=[0-9])(?=[A-Z])/', '_', $violation->path()));

                return $violation->isError()
                    ? Violation::error($path, $violation->code(), $violation->message(), $violation->params())
                    : Violation::warning($path, $violation->code(), $violation->message(), $violation->params());
            },
            $result->all(),
        ));
    }
}
