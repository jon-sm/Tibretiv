<?php

declare(strict_types=1);

namespace App\CandidateApplication\Application\ListCandidateApplications;

use App\Shared\Application\Bus\Query\Query;

/**
 * Answered with an CandidateApplicationsPage, newest first.
 */
final readonly class ListCandidateApplications implements Query
{
    public const int PER_PAGE = 20;

    public function __construct(
        public ?string $search = null,
        public ?string $positionId = null,
        /** received | contacted | rejected | hired */
        public ?string $status = null,
        /** pending | completed | failed */
        public ?string $enrichmentStatus = null,
        public ?int $minScore = null,
        /** Y-m-d, a whole day in the business timezone, inclusive */
        public ?string $appliedFrom = null,
        /** Y-m-d, a whole day in the business timezone, inclusive */
        public ?string $appliedTo = null,
        /** 1-based. Past the last page, the last page is returned. */
        public int $page = 1,
    ) {
    }
}
