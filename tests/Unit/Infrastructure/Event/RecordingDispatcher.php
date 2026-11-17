<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Event;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * PSR-14 dispatcher fake recording every dispatched object, in order.
 */
final class RecordingDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    private array $dispatched = [];

    public function dispatch(object $event): object
    {
        $this->dispatched[] = $event;

        return $event;
    }

    /**
     * @return list<object>
     */
    public function dispatched(): array
    {
        return $this->dispatched;
    }
}
