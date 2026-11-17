<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message;

use SensitiveParameter;

/**
 * Non-blocking message `Erreur` of a 200 answer (`erreursNonBloquantes`, api-reference §1.3, §3.6): the code is kept raw
 * (trimmed; an absent `code` is decoded as an empty string, api-reference §5, the PDF shows trailing spaces) and typed when known. The description may echo host values: it is personal
 * data, masked in debug output (ADR 0007), never put in exception messages.
 */
final readonly class NonBlockingMessage
{
    private function __construct(
        private string $code,
        private ?string $message,
        private ?string $description,
    ) {}

    public static function of(string $code, ?string $message, #[SensitiveParameter] ?string $description): self
    {
        return new self(trim($code), $message, $description);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function knownCode(): ?NonBlockingCode
    {
        return NonBlockingCode::tryFrom($this->code);
    }

    public function message(): ?string
    {
        return $this->message;
    }

    public function description(): ?string
    {
        return $this->description;
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
