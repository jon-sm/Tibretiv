<?php

declare(strict_types=1);

namespace App\CandidateApplication\Application\Port;

final readonly class CvAnalysis
{
    public function __construct(
        public string $summary,
        /** 0 to 100: relevance of the CV for the position */
        public int $score,
    ) {
    }
}
