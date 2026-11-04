<?php

declare(strict_types=1);

namespace App\CandidateApplication\Application\Read;

/**
 * Filters for the application list. Every one is optional; set ones are combined with AND.
 */
final readonly class CandidateApplicationCriteria
{
    public function __construct(
        /** Case-insensitive; each word must match the name, surname, email or canonical email. */
        public ?string $search = null,
        public ?string $positionId = null,
        public ?string $status = null,
        public ?string $enrichmentStatus = null,
        /** Only completed enrichments can match. */
        public ?int $minScore = null,
        /** Inclusive, UTC */
        public ?\DateTimeImmutable $appliedFrom = null,
        /** Exclusive, UTC */
        public ?\DateTimeImmutable $appliedBefore = null,
    ) {
    }
}
