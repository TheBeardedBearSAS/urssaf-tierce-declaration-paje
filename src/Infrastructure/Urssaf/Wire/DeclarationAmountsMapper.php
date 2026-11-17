<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationAmounts;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\WithholdingTax;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * `DeclarationCommun` (api-reference §2.7), the computed amounts of 045 / 046 / 050 / 051. Every field is optional in
 * the Swagger and none is bounded (ADR 0008): an absent (or null) field is null, `txPas` is an exact {@see Rate} and
 * the two planned dates are strict `Y-m-d` dates (the placeholder "string" of the official example is refused,
 * ADR 0001). A field of another type is refused, naming the field and never its value.
 */
final class DeclarationAmountsMapper
{
    /**
     * @throws UnexpectedUrssafResponse
     */
    public static function map(Payload $declaration): DeclarationAmounts
    {
        return DeclarationAmounts::of(
            self::amount($declaration, 'mntCotisations'),
            self::amount($declaration, 'mntCotiPriseEnChargeOdpf'),
            self::amount($declaration, 'mntCotiEmplAcharge'),
            WithholdingTax::of(
                self::amount($declaration, 'mntSalaireNetImposable'),
                RateEncoding::decodeOptional($declaration->numeric('txPas'), 'txPas'),
                self::amount($declaration, 'mntImpotPas'),
                self::amount($declaration, 'mntSalaireNetApayerApresPas'),
            ),
            self::amount($declaration, 'mntEmplCmgRemuneration'),
            self::amount($declaration, 'mntEmplAverserSalarie'),
            self::amount($declaration, 'mntAverserParPaje'),
            self::amount($declaration, 'mntApreleverParPaje'),
            self::date($declaration, 'dtPrelevementPrevisionnelle'),
            self::date($declaration, 'dtVersementPrevisionnelle'),
        );
    }

    private static function amount(Payload $declaration, string $field): ?Amount
    {
        return AmountEncoding::decodeOptional($declaration->numeric($field), $field);
    }

    private static function date(Payload $declaration, string $field): ?CalendarDate
    {
        $value = $declaration->optionalString($field);

        return $value === null ? null : DateEncoding::decode($value, $field);
    }
}
