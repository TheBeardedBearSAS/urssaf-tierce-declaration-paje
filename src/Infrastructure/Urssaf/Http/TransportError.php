<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

/**
 * Sanitised cause of a transport failure (ADR 0007, ADR 0002): only the class of the PSR-18 exception is kept.
 *
 * The PSR-18 message usually embeds the full URI, query included (NIR, names, birth dates on 020/021), so neither
 * that message nor the original exception ever travels in the exception chain.
 *
 * @internal
 */
final class TransportError extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function fromClientException(ClientExceptionInterface $exception): self
    {
        return new self(\sprintf('The PSR-18 client failed (%s).', $exception::class));
    }
}
