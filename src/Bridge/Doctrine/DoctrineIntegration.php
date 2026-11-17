<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver;
use ReflectionClass;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\BirthDateType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\CalendarDateType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\DeclarationFingerprintType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\DeclarationRecordIdType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\DeclarationResultType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\EmployerAccountIdType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\EmployerVerificationType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\ExternalReferenceType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\InstantType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\MandateIdType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\PajeEmployeeNumberType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\PajeEmployerNumberType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\SubmissionErrorsType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\TenantIdType;

/**
 * Everything a host application needs to persist the three aggregates with Doctrine ORM 3 / DBAL 4.
 *
 * The domain model carries no Doctrine metadata: the mapping lives in XML files (Resources/mapping) whose names follow
 * the Symfony "simplified" convention for the namespace prefix {@see self::MAPPING_NAMESPACE}. A plain application does
 *
 *     DoctrineIntegration::registerTypes();
 *     $configuration->setMetadataDriverImpl(DoctrineIntegration::mappingDriver());
 *
 * and a Symfony application prepends {@see self::doctrineBundleConfiguration()} to the "doctrine" extension.
 */
final class DoctrineIntegration
{
    /**
     * Namespace prefix of the mapped classes (Domain and Bridge entities): file "Domain.Employer.EmployerAccount.orm.xml"
     * maps "<prefix>\Domain\Employer\EmployerAccount".
     */
    public const MAPPING_NAMESPACE = 'TheBeardedBear\UrssafTierceDeclarationPaje';

    public const MAPPING_ALIAS = 'UrssafTierceDeclarationPaje';

    /**
     * Prefix of the tables created by the mapping.
     */
    public const TABLE_PREFIX = 'urssaf_paje_';

    public static function mappingDirectory(): string
    {
        return __DIR__ . '/Resources/mapping';
    }

    /**
     * Metadata driver for the mapping files, for applications that configure the ORM without the Symfony bundle.
     */
    public static function mappingDriver(): SimplifiedXmlDriver
    {
        return new SimplifiedXmlDriver([self::mappingDirectory() => self::MAPPING_NAMESPACE]);
    }

    /**
     * The custom DBAL types of the mapping, by registration name.
     *
     * @return array<non-empty-string, class-string<Type>>
     */
    public static function types(): array
    {
        return [
            TenantIdType::NAME => TenantIdType::class,
            ExternalReferenceType::NAME => ExternalReferenceType::class,
            EmployerAccountIdType::NAME => EmployerAccountIdType::class,
            MandateIdType::NAME => MandateIdType::class,
            DeclarationRecordIdType::NAME => DeclarationRecordIdType::class,
            PajeEmployerNumberType::NAME => PajeEmployerNumberType::class,
            PajeEmployeeNumberType::NAME => PajeEmployeeNumberType::class,
            DeclarationFingerprintType::NAME => DeclarationFingerprintType::class,
            CalendarDateType::NAME => CalendarDateType::class,
            BirthDateType::NAME => BirthDateType::class,
            InstantType::NAME => InstantType::class,
            EmployerVerificationType::NAME => EmployerVerificationType::class,
            SubmissionErrorsType::NAME => SubmissionErrorsType::class,
            DeclarationResultType::NAME => DeclarationResultType::class,
        ];
    }

    /**
     * Registers the custom types in the global DBAL registry. Idempotent.
     */
    public static function registerTypes(): void
    {
        foreach (self::types() as $name => $class) {
            if (Type::hasType($name)) {
                continue;
            }
            Type::addType($name, $class);
        }
    }

    /**
     * Whether native lazy objects (PHP 8.4 lazy objects, used instead of generated proxies) can be enabled: they need
     * PHP 8.4 and an ORM that implements them (3.4+).
     */
    public static function nativeLazyObjectsAvailable(): bool
    {
        return self::runtimeNativeLazyObjectsOptions() !== [];
    }

    /**
     * The "orm" options that enable the native lazy objects, or none when the runtime cannot use them.
     *
     * @param class-string $ormConfiguration the ORM configuration class, which has "enableNativeLazyObjects()" from ORM 3.4
     *
     * @return array{enable_native_lazy_objects?: true}
     */
    public static function nativeLazyObjectsOptions(int $phpVersionId, string $ormConfiguration): array
    {
        if ($phpVersionId >= 80_400 && (new ReflectionClass($ormConfiguration))->hasMethod('enableNativeLazyObjects')) {
            return ['enable_native_lazy_objects' => true];
        }

        return [];
    }

    /**
     * Configuration to prepend to the "doctrine" extension of doctrine-bundle: the types, the XML mapping of the
     * default entity manager (or of the entity manager named by the host, under "orm.entity_managers.<name>.mappings")
     * and, when PHP and the ORM support them, the native lazy objects (symfony/var-exporter dropped the lazy ghosts the
     * ORM proxies rely on in version 8). The host can still override it in its own "doctrine" configuration.
     *
     * @return array{
     *     dbal: array{types: array<non-empty-string, class-string<Type>>},
     *     orm: array{enable_native_lazy_objects?: true, mappings: array<string, array{type: string, is_bundle: bool, dir: string, prefix: string, alias: string}>}
     * }
     */
    public static function doctrineBundleConfiguration(): array
    {
        return self::doctrineBundleConfigurationWith(self::runtimeNativeLazyObjectsOptions());
    }

    /**
     * The configuration to prepend, with the given options on top of the "orm" section.
     *
     * @param array{enable_native_lazy_objects?: true} $ormOptions
     *
     * @return array{
     *     dbal: array{types: array<non-empty-string, class-string<Type>>},
     *     orm: array{enable_native_lazy_objects?: true, mappings: array<string, array{type: string, is_bundle: bool, dir: string, prefix: string, alias: string}>}
     * }
     */
    public static function doctrineBundleConfigurationWith(array $ormOptions): array
    {
        return [
            'dbal' => ['types' => self::types()],
            'orm' => $ormOptions + [
                'mappings' => [
                    self::MAPPING_ALIAS => [
                        'type' => 'xml',
                        'is_bundle' => false,
                        'dir' => self::mappingDirectory(),
                        'prefix' => self::MAPPING_NAMESPACE,
                        'alias' => self::MAPPING_ALIAS,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{enable_native_lazy_objects?: true}
     */
    private static function runtimeNativeLazyObjectsOptions(): array
    {
        return self::nativeLazyObjectsOptions(\PHP_VERSION_ID, Configuration::class);
    }
}
