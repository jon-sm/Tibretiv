<?php

declare(strict_types=1);

namespace App\CandidateApplication\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;

final readonly class CandidateApplicationStatusChanged implements DomainEvent
{
    public function __construct(
        public string $applicationId,
        public string $from,
        public string $to,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
