<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service;

/**
 * The URSSAF call of a submission a failure belongs to (ADR 0003, ADR 0008): a pre-declaration (045 / 046) records
 * nothing, a declaration (050 / 051) may have been recorded even when the answer is lost.
 */
enum SubmissionStage
{
    case PreDeclaration;
    case Declaration;

    /**
     * Whether a failure of an unknown origin at this stage may mean that URSSAF recorded the declaration.
     */
    public function mayHaveRecorded(): bool
    {
        return $this === self::Declaration;
    }
}
