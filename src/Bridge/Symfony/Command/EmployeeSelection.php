<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command;

/**
 * The employee as selected on the command line, before any prompt: exactly one source (number, NIR to ask, or the decoded
 * identity file) already validated, so that an invalid usage is refused before the operator types anything.
 */
final readonly class EmployeeSelection
{
    /**
     * @param array<array-key, mixed>|null $identity the decoded --identity-payload file
     */
    public function __construct(
        public ?string $number,
        public bool $askNir,
        public bool $askBirthDate,
        public ?array $identity,
    ) {}
}
