<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

/**
 * Something that happened in the domain. Implementations must be serializable
 * because events are delivered asynchronously.
 */
interface DomainEvent
{
}
