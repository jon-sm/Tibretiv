<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus\Event;

/**
 * Reacts to one DomainEvent through an __invoke(YourEvent $event): void method.
 * Runs in the worker, and a failed message is retried, so it must be idempotent.
 */
interface EventHandler
{
}
