<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Clock;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use App\Shared\Domain\Clock\Clock;
use Symfony\Component\Clock\ClockInterface;

/**
 * Adapts Symfony's clock, which tests replace with a MockClock.
 */
#[AsAlias(Clock::class)]
final readonly class SymfonyClock implements Clock
{
    public function __construct(private ClockInterface $clock)
    {
    }

    public function now(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
    }
}
