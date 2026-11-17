<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake;

use Override;
use ReflectionClass;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\EventPublisher;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\DomainEvent;

final class RecordingEventPublisher implements EventPublisher
{
    /** @var list<DomainEvent> */
    private array $events = [];

    public function __construct(private readonly Journal $journal) {}

    #[Override]
    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->events[] = $event;
            $this->journal->add('publish:' . (new ReflectionClass($event))->getShortName());
        }
    }

    /**
     * @return list<DomainEvent>
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * @return list<string> short class names of the published events
     */
    public function names(): array
    {
        return array_map(static fn(DomainEvent $event): string => (new ReflectionClass($event))->getShortName(), $this->events);
    }
}
