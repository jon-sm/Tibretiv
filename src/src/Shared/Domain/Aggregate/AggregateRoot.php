<?php

declare(strict_types=1);

namespace App\Shared\Domain\Aggregate;

use App\Shared\Domain\Event\DomainEvent;

/**
 * Records the domain events raised by a change, for the application layer to publish
 * once the change is saved.
 */
abstract class AggregateRoot
{
    /** @var list<DomainEvent> */
    private array $events = [];

    /** @return list<DomainEvent> */
    final public function pullEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    final protected function record(DomainEvent $event): void
    {
        $this->events[] = $event;
    }
}
