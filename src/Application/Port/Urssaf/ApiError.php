<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;

/**
 * Error item returned by URSSAF: {"code": "...", "message": "...", "description": "..."} (api-reference §1.3). Kept
 * verbatim (French); message and description may be absent. The description may echo host values (personal data):
 * masked in debug output, never put in an exception message (ADR 0007).
 */
final readonly class ApiError
{
    private function __construct(private string $code, private ?string $message, private ?string $description) {}

    /**
     * @throws InvariantViolation "api_error.empty_code"
     */
    public static function of(string $code, ?string $message, #[SensitiveParameter] ?string $description): self
    {
        $trimmed = trim($code);
        if ($trimmed === '') {
            throw InvariantViolation::because('api_error.empty_code', 'An URSSAF error must carry a code.');
        }

        return new self($trimmed, $message, $description);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function message(): ?string
    {
        return $this->message;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function hasCode(string $code): bool
    {
        return $this->code === $code;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code
            && $this->message === $other->message
            && $this->description === $other->description;
    }

    /**
     * @return array<string, string|null>
     */
    public function __debugInfo(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'description' => $this->description === null ? null : '***',
        ];
    }
}
