<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic;

/**
 * One line of the `urssaf:paje:check` report.
 */
final readonly class CheckResult
{
    /**
     * @param string $check   what is checked ("credentials", "token store"...)
     * @param string $subject the tenant or resource checked, empty when global
     * @param string $detail  what was found, and what to do when it is not ok
     */
    public function __construct(
        public string $check,
        public string $subject,
        public CheckStatus $status,
        public string $detail,
    ) {}
}
