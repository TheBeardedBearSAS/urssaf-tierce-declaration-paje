<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Validation;

use PHPUnit\Framework\Assert;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Yaml\Yaml;

/**
 * The violation codes of the package, found by reading the sources: literal codes, then the codes composed from a
 * subject and a reason ("<subject>.<reason>"), which is how InvalidValue, the violation collector and the raw fields
 * build them. One scanner step per way of composing a code.
 *
 * A new way of composing a code must be taught here: {@see self::DYNAMIC_SUBJECT_FILES} lists the files that give a
 * variable subject to a factory, and fails the test until the new file is mapped.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class ViolationCodes
{
    public const CODE = '[a-z][a-z0-9_]*';

    /** Directories scanned for codes, relative to the package root. */
    private const SCANNED = [
        'src/Domain',
        'src/Application',
        'src/Infrastructure',
    ];

    /**
     * Literals that look like a code and are not (a token scope, a path...).
     */
    public const NOT_CODES = [
        'creation.identity',
        'tiersdecl.paje',
    ];

    /**
     * Files that give a variable subject to a factory: their codes come from the callers, which the scanner step
     * "constraint" reads. A new file fails the test until it is mapped there.
     */
    public const DYNAMIC_SUBJECT_FILES = [
        'src/Domain/Employee/Address.php',
        'src/Domain/Shared/AmountRules.php',
        'src/Domain/Shared/Decimal.php',
        'src/Domain/Shared/HalfDays.php',
        'src/Domain/Shared/PostalCode.php',
        'src/Domain/Shared/StringConstraint.php',
        'src/Domain/Shared/UpperCaseName.php',
        'src/Domain/Validation/ViolationCollector.php',
    ];

    /** What a variable subject of the file gives when no caller passes a subject (the default of the parameter). */
    private const DEFAULT_SUBJECTS = [
        'src/Domain/Shared/PostalCode.php' => ['postal_code', ['.too_short', '.too_long']],
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $suffixes = self::suffixMap();
        $codes = [];
        foreach (self::sources() as $path => $content) {
            $constants = self::constants($content);
            array_push(
                $codes,
                ...self::literalCodes($content),
                ...self::factoryCodes($path, $content, $constants, $suffixes),
                ...self::constraintCodes($path, $content, $constants),
                ...self::collectorCodes($content),
                ...self::fieldCodes($content),
                ...self::prefixedCodes($path, $content, $constants),
            );
        }
        foreach (self::DEFAULT_SUBJECTS as [$subject, $reasons]) {
            foreach ($reasons as $reason) {
                $codes[] = $subject . $reason;
            }
        }
        $codes = array_values(array_diff(array_unique($codes), self::NOT_CODES));
        sort($codes, SORT_STRING);

        return $codes;
    }

    /**
     * The files that give a variable subject to a factory.
     *
     * @return list<string>
     */
    public static function dynamicSubjectFiles(): array
    {
        $found = array_keys(array_filter(
            self::sources(),
            static fn(string $content): bool => preg_match('/(?:InvalidValue::(?!because\b)\w+|StringConstraint::\w+)\(\s*(?:\$\w+,\s*)?\$subject\b/', $content) === 1,
        ));
        sort($found);

        return $found;
    }

    /**
     * @return array<string, string> the sources by path relative to the package root
     */
    public static function sources(): array
    {
        $sources = [];
        foreach (self::SCANNED as $directory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::root() . '/' . $directory, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($files as $file) {
                Assert::assertInstanceOf(SplFileInfo::class, $file);
                if ($file->getExtension() === 'php') {
                    $sources[substr($file->getPathname(), \strlen(self::root()) + 1)] = (string) file_get_contents($file->getPathname());
                }
            }
        }
        ksort($sources);

        return $sources;
    }

    /**
     * The shipped messages of a locale, by violation code.
     *
     * @return array<string, string>
     */
    public static function messages(string $locale): array
    {
        // @mago-expect analysis:mixed-assignment YAML is untyped; every entry is checked below.
        $parsed = Yaml::parseFile(self::root() . '/src/Bridge/Symfony/Resources/translations/urssaf_tierce_declaration_paje.' . $locale . '.yaml');
        Assert::assertIsArray($parsed);
        $messages = [];
        foreach ($parsed as $code => $message) { // @mago-expect analysis:mixed-assignment narrowed by the assertion below.
            Assert::assertIsString($message);
            $messages[(string) $code] = $message;
        }

        return $messages;
    }

    public static function root(): string
    {
        return \dirname(__DIR__, 4);
    }

    /**
     * The reason suffix of every InvalidValue factory taking a subject, read from InvalidValue itself:
     * "empty" => ".empty", "tooLong" => ".too_long"...
     *
     * @return array<string, string>
     */
    public static function suffixMap(): array
    {
        $content = (string) file_get_contents(self::root() . '/src/Domain/Exception/InvalidValue.php');
        $map = [];
        foreach (\array_slice(explode('public static function ', $content), 1) as $method) {
            $name = [];
            if (preg_match('/^(\w+)\(string \$subject/', $method, $name) !== 1) {
                continue;
            }
            $suffix = [];
            Assert::assertSame(1, preg_match('/\$subject \. \'(\.[a-z_]+)\'/', $method, $suffix), 'No suffix in InvalidValue::' . $name[1]);
            $map[$name[1]] = $suffix[1];
        }

        return $map;
    }

    /**
     * The scalar constants of a file, `const NAME = 'value';`.
     *
     * @return array<string, string>
     */
    private static function constants(string $content): array
    {
        $matches = [];
        preg_match_all('/const ([A-Z_0-9]+) = \'(' . self::CODE . ')\';/', $content, $matches, PREG_SET_ORDER);
        $constants = [];
        foreach ($matches as $match) {
            $constants[$match[1]] = $match[2];
        }

        return $constants;
    }

    /**
     * @param array<string, string> $constants
     */
    private static function resolve(string $literal, string $constant, array $constants, string $path): string
    {
        if ($literal !== '') {
            return $literal;
        }
        Assert::assertArrayHasKey($constant, $constants, 'Unresolved constant ' . $constant . ' in ' . $path);

        return $constants[$constant];
    }

    /**
     * 1. The literal codes.
     *
     * @return list<string>
     */
    private static function literalCodes(string $content): array
    {
        $matches = [];
        preg_match_all('/\'(' . self::CODE . '(?:\.' . self::CODE . ')+)\'/', $content, $matches);

        return array_values($matches[1]);
    }

    /**
     * 2. The codes composed from a literal or constant subject by a factory of a known reason.
     *
     * @param array<string, string> $constants
     * @param array<string, string> $suffixes
     *
     * @return list<string>
     */
    private static function factoryCodes(string $path, string $content, array $constants, array $suffixes): array
    {
        $codes = [];
        $matches = [];
        preg_match_all('/InvalidValue::(\w+)\(\s*(?:\'(' . self::CODE . ')\'|self::([A-Z_0-9]+))/', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (!\array_key_exists($match[1], $suffixes)) {
                continue;
            }
            $codes[] = self::resolve($match[2] ?? '', $match[3] ?? '', $constants, $path) . $suffixes[$match[1]];
        }
        foreach (['RecordNotFound::withIdentifier' => '.not_found', 'InvalidStateTransition::between' => '.invalid_state_transition'] as $call => $suffix) {
            preg_match_all('/' . preg_quote($call, '/') . '\(\s*(?:\'(' . self::CODE . ')\'|self::([A-Z_0-9]+))/', $content, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $codes[] = self::resolve($match[1] ?? '', $match[2] ?? '', $constants, $path) . $suffix;
            }
        }

        return $codes;
    }

    /**
     * 3. The codes of the string and decimal constraints, the amount rules and the half-day ceiling, whose subject is
     * a literal or a constant of the calling file (the variable subjects are mapped by {@see self::dynamicSubjects()}).
     *
     * @param array<string, string> $constants
     *
     * @return list<string>
     */
    private static function constraintCodes(string $path, string $content, array $constants): array
    {
        $codes = [];
        $subject = '(?:\'(' . self::CODE . ')\'|self::([A-Z_0-9]+))';
        $rules = [
            // call => [pattern, reasons]
            'StringConstraint::length' => ['/StringConstraint::length\(\s*\$\w+,\s*' . $subject . '/', ['.too_short', '.too_long']],
            'StringConstraint::atMost' => ['/StringConstraint::atMost\(\s*\$\w+,\s*' . $subject . '/', ['.too_long']],
            'StringConstraint::matches' => ['/StringConstraint::matches\(\s*\$\w+,\s*' . $subject . '/', ['.invalid_format']],
            'UpperCaseName::of' => ['/UpperCaseName::of\([^;]*?,\s*' . $subject . '\)/', ['.too_long', '.invalid_format']],
            'Decimal::parse' => ['/Decimal::parse\(\s*\$\w+,\s*' . $subject . '/', ['.negative', '.invalid_format', '.too_many_decimals', '.too_large']],
            'Decimal::parseSigned' => ['/Decimal::parseSigned\(\s*\$\w+,\s*' . $subject . '/', ['.invalid_format', '.too_many_decimals', '.too_large']],
            'Address::line' => ['/self::line\(\s*' . $subject . '/', ['.too_long', '.invalid_format']],
            'assertAtMost' => ['/->assertAtMost\([^;]*?,\s*' . $subject . '\s*,?\s*\)/', ['.out_of_range']],
        ];
        foreach ($rules as [$pattern, $reasons]) {
            $matches = [];
            preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $base = self::resolve($match[1] ?? '', $match[2] ?? '', $constants, $path);
                foreach ($reasons as $reason) {
                    $codes[] = $base . $reason;
                }
            }
        }
        $amounts = ['capped' => ['.too_large'], 'nonNegativeCapped' => ['.negative', '.too_large'], 'positiveCapped' => ['.not_positive', '.too_large']];
        foreach ($amounts as $rule => $reasons) {
            $matches = [];
            preg_match_all('/AmountRules::' . $rule . '\(\s*\$collector,\s*\'\w+\',\s*\'(' . self::CODE . ')\'/', $content, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                foreach ($reasons as $reason) {
                    $codes[] = $match[1] . $reason;
                }
            }
        }

        return $codes;
    }

    /**
     * 4. The codes of the violation collector: "<subject>.required" and "<subject>.out_of_range".
     *
     * @return list<string>
     */
    private static function collectorCodes(string $content): array
    {
        $codes = [];
        $matches = [];
        preg_match_all('/->(required|requiredInteger|optionalInteger|requiredBool)\(\s*\'\w+\',\s*\'(' . self::CODE . ')\'/', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if ($match[1] !== 'optionalInteger') {
                $codes[] = $match[2] . '.required';
            }
            if ($match[1] === 'requiredInteger' || $match[1] === 'optionalInteger') {
                $codes[] = $match[2] . '.out_of_range';
            }
        }

        return $codes;
    }

    /**
     * 5. The codes of the raw fields: "<key>.invalid_type" for every key read, "<key>.required" for the required ones.
     *
     * @return list<string>
     */
    private static function fieldCodes(string $content): array
    {
        $codes = [];
        $matches = [];
        preg_match_all('/\$fields->(\w+)\(\s*\'(' . self::CODE . ')\'/', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (!\in_array($match[1], ['text', 'integer', 'boolean', 'requiredText', 'requiredInteger', 'requiredBoolean', 'required', 'optional', 'nested', 'optionalNested', 'items'], true)) {
                continue;
            }
            $codes[] = $match[2] . '.invalid_type';
            if (str_starts_with($match[1], 'required')) {
                $codes[] = $match[2] . '.required';
            }
        }

        return $codes;
    }

    /**
     * 6. The codes composed from a constant prefix and a literal suffix.
     *
     * @param array<string, string> $constants
     *
     * @return list<string>
     */
    private static function prefixedCodes(string $path, string $content, array $constants): array
    {
        $codes = [];
        $matches = [];
        preg_match_all('/self::([A-Z_0-9]+) \. \'(\.' . self::CODE . ')\'/', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $codes[] = self::resolve('', $match[1], $constants, $path) . $match[2];
        }

        return $codes;
    }
}
