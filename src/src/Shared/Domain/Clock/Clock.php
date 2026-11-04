<?php

declare(strict_types=1);

namespace App\Shared\Domain\Clock;

interface Clock
{
    /** The current instant, in UTC. */
    public function now(): \DateTimeImmutable;
}
