<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto;

/**
 * Number of declarations moved back from invalid_credentials to pending.
 */
final readonly class RequeueReport
{
    public function __construct(public int $declarations) {}
}
