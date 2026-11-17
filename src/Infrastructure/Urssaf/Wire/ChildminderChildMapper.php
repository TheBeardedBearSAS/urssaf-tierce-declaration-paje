<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderChild;

/**
 * `InputEnfantAma` (api-reference §2.7): the `InputEnfant` members of {@see ChildMapper} followed by
 * `joursAccueilHandicap` and `joursAccueil24Consecutive`, each emitted only when set (0 is a value).
 */
final class ChildminderChildMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function encode(#[SensitiveParameter] ChildminderChild $child): array
    {
        return ChildMapper::encode($child->child()) + OptionalWireValues::present([
            'joursAccueilHandicap' => $child->disabledChildCareDays(),
            'joursAccueil24Consecutive' => $child->consecutive24hDays(),
        ]);
    }
}
