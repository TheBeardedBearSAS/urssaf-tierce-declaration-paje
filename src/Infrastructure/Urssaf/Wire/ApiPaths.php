<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\UrssafEndpoints;

/**
 * Paths of the 12 operations (api-reference §2), relative to {@see UrssafEndpoints::API_BASE_PATH}.
 *
 * The employer number is an identifier of the platform's own customer but still personal data (ADR 0007): a path must
 * not be logged any more than a query string.
 *
 * One method per operation, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final class ApiPaths
{
    private const MANDATES = '/mandats';

    /**
     * 020, `GET`.
     */
    public static function verifyEmployer(PajeEmployerNumber $employer): string
    {
        return self::employer($employer) . '/verifier';
    }

    /**
     * 021, `POST`.
     */
    public static function verifyEmployee(): string
    {
        return '/salaries/verifier';
    }

    /**
     * 022, `POST`.
     */
    public static function verifyChildren(PajeEmployerNumber $employer): string
    {
        return self::employer($employer) . '/enfants/verifier';
    }

    /**
     * 023, `POST`.
     */
    public static function associateEmployee(PajeEmployerNumber $employer): string
    {
        return self::employer($employer) . '/salarie/associer';
    }

    /**
     * 040, `POST`.
     */
    public static function estimateChildminder(): string
    {
        return '/ama/estimer';
    }

    /**
     * 041, `POST`.
     */
    public static function estimateHomeCare(): string
    {
        return '/ged/estimer';
    }

    /**
     * 045, `POST`.
     */
    public static function preDeclareChildminder(PajeEmployerNumber $employer): string
    {
        return self::employer($employer) . '/ama/predeclarer';
    }

    /**
     * 046, `POST`.
     */
    public static function preDeclareHomeCare(PajeEmployerNumber $employer): string
    {
        return self::employer($employer) . '/ged/predeclarer';
    }

    /**
     * 050, `POST`.
     */
    public static function declareChildminder(PajeEmployerNumber $employer): string
    {
        return self::employer($employer) . '/ama/declarer';
    }

    /**
     * 051, `POST`.
     */
    public static function declareHomeCare(PajeEmployerNumber $employer): string
    {
        return self::employer($employer) . '/ged/declarer';
    }

    /**
     * 030, `POST` (query parameters, no body).
     */
    public static function registerMandate(): string
    {
        return self::MANDATES;
    }

    /**
     * 031, `DELETE` (query parameters, no body).
     */
    public static function cancelMandate(): string
    {
        return self::MANDATES;
    }

    /**
     * `/employeurs/{numeroPajeEmployeur}`, the number encoded as a path segment.
     */
    public static function employer(PajeEmployerNumber $employer): string
    {
        return '/employeurs/' . rawurlencode($employer->value());
    }
}
