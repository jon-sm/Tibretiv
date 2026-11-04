<?php

declare(strict_types=1);

namespace App\CandidateApplication\Application\Read;

final readonly class EnrichmentState
{
    public function __construct(
        public string $applicationId,
        /** pending | completed | failed */
        public string $status,
        public ?string $summary,
        public ?int $score,
        public ?\DateTimeImmutable $enrichedAt,
    ) {
    }
}
