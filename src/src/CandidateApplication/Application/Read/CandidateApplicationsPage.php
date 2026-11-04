<?php

declare(strict_types=1);

namespace App\CandidateApplication\Application\Read;

final readonly class CandidateApplicationsPage
{
    /** Number of pages, at least 1 so an empty result is still "page 1 of 1". */
    public int $pages;

    /** @param list<CandidateApplicationView> $items */
    public function __construct(
        public array $items,
        /** Matching applications, across all pages */
        public int $total,
        public int $page,
        public int $perPage,
    ) {
        $this->pages = max(1, (int) ceil($total / $perPage));
    }

    public function hasPendingEnrichment(): bool
    {
        foreach ($this->items as $item) {
            if ($item->isEnrichmentPending()) {
                return true;
            }
        }

        return false;
    }
}
