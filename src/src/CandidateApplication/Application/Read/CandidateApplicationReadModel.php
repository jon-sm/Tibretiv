<?php

declare(strict_types=1);

namespace App\CandidateApplication\Application\Read;

interface CandidateApplicationReadModel
{
    public function count(CandidateApplicationCriteria $criteria): int;

    /**
     * Newest appliedAt first. The views have no position: the query handler resolves them.
     *
     * @return list<CandidateApplicationView>
     */
    public function search(CandidateApplicationCriteria $criteria, int $offset, int $limit): array;

    public function byId(string $id): ?CandidateApplicationView;

    /**
     * @param list<string> $ids
     *
     * @return list<EnrichmentState>
     */
    public function enrichmentStates(array $ids): array;
}
