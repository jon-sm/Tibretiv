<?php

declare(strict_types=1);

namespace App\CandidateApplication\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;

final readonly class CandidateApplicationSubmitted implements DomainEvent
{
    public function __construct(
        public string $applicationId,
        public string $positionId,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
