<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Validation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Validation\ViolationListConverter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\Violation;

/**
 * One test per conversion rule (errors, warnings, prefix, translation, parameters), hence the method count.
 */
final class ViolationListConverterTest extends TestCase
{
    public function testEveryErrorBecomesAConstraintViolationWithTheCodeAndThePath(): void
    {
        $error = Violation::error('address.postalCode', 'postal_code.too_short', 'Postal code is too short.');

        $list = (new ViolationListConverter(null))->convert(ValidationResult::of($error));

        static::assertCount(1, $list);
        $violation = self::first($list);
        static::assertSame('Postal code is too short.', $violation->getMessage());
        static::assertSame('postal_code.too_short', $violation->getMessageTemplate());
        static::assertSame('postal_code.too_short', $violation->getCode());
        static::assertSame('address.postalCode', $violation->getPropertyPath());
        static::assertSame([], $violation->getParameters());
        static::assertNull($violation->getRoot());
        static::assertNull($violation->getInvalidValue());
        static::assertNull($violation->getPlural());
        static::assertNull($violation->getConstraint());
        static::assertSame($error, $violation->getCause());
    }

    public function testTheViolationsOfAValidationFailureAreConverted(): void
    {
        $result = ValidationResult::of(
            Violation::error('birthDate', 'calendar_date.invalid_format', 'The date has an invalid format.'),
            Violation::error('nir', 'nir.too_short', 'NIR is too short.'),
        );

        $list = (new ViolationListConverter(null))->convert(ValidationFailed::withResult($result));

        static::assertSame(['birthDate', 'nir'], self::paths($list));
    }

    public function testThePathPrefixIsPrependedToEveryPath(): void
    {
        $result = ValidationResult::of(
            Violation::error('address.postalCode', 'postal_code.required', 'The postal code is required.'),
            Violation::error('', 'children.empty', 'At least one child is required.'),
        );

        $list = (new ViolationListConverter(null))->convert($result, 'declaration');

        static::assertSame(['declaration.address.postalCode', 'declaration'], self::paths($list));
    }

    public function testWarningsAreKeptOnlyOnRequest(): void
    {
        $result = ValidationResult::of(
            Violation::warning('', 'children.empty', 'No child is given.'),
            Violation::error('hours', 'hours.out_of_range', 'Hours is outside the accepted range.'),
        );
        $converter = new ViolationListConverter(null);

        static::assertSame(['hours'], self::paths($converter->convert($result)));
        static::assertSame(['', 'hours'], self::paths($converter->convertWithWarnings($result, '')));
    }

    public function testTheParametersBecomePlaceholdersAndAreGivenToTheTranslator(): void
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'hours.out_of_range' => 'Entre %min% et %max% (%ratio%, %allowed%, %blocked%, %unit%).',
        ], 'fr', 'urssaf_tierce_declaration_paje');
        $translator->addResource('array', ['hours.out_of_range' => 'Wrong domain.'], 'fr', 'messages');
        $error = Violation::error('hours', 'hours.out_of_range', 'Hours must be between 0 and 300.', [
            'min' => 0,
            'max' => 300,
            'ratio' => 0.5,
            'allowed' => true,
            'blocked' => false,
            'unit' => 'HEURE',
        ]);

        $violation = self::first((new ViolationListConverter($translator))->convert(ValidationResult::of($error)));

        $parameters = ['%min%' => '0', '%max%' => '300', '%ratio%' => '0.5', '%allowed%' => 'true', '%blocked%' => 'false', '%unit%' => 'HEURE'];
        static::assertSame('Entre 0 et 300 (0.5, true, false, HEURE).', $violation->getMessage());
        static::assertSame($parameters, $violation->getParameters());
    }

    public function testTheShippedFrenchMessageIsUsedWhenATranslatorIsGiven(): void
    {
        $translator = new Translator('fr');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', __DIR__ . '/../../../../src/Bridge/Symfony/Resources/translations/urssaf_tierce_declaration_paje.fr.yaml', 'fr', 'urssaf_tierce_declaration_paje');
        $result = ValidationResult::of(Violation::error('nir', 'nir.too_short', 'NIR is too short.'));

        $violation = self::first((new ViolationListConverter($translator))->convert($result));

        static::assertSame('Le champ « NIR » est trop court.', $violation->getMessage());
    }

    private static function first(ConstraintViolationList $list): ConstraintViolation
    {
        $violation = $list->get(0);
        static::assertInstanceOf(ConstraintViolation::class, $violation);

        return $violation;
    }

    /**
     * @return list<string>
     */
    private static function paths(ConstraintViolationList $list): array
    {
        $paths = [];
        foreach ($list as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        return $paths;
    }
}
