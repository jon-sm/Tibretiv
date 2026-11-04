<?php

declare(strict_types=1);

namespace App\CandidateApplication\Application\GetEnrichmentStates;

use App\CandidateApplication\Application\Read\CandidateApplicationReadModel;
use App\CandidateApplication\Application\Read\EnrichmentState;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetEnrichmentStatesHandler implements QueryHandler
{
    public function __construct(private CandidateApplicationReadModel $applications)
    {
    }

    /** @return list<EnrichmentState> */
    public function __invoke(GetEnrichmentStates $query): array
    {
        $ids = \array_slice(array_values(array_unique($query->applicationIds)), 0, GetEnrichmentStates::MAX_IDS);

        return [] === $ids ? [] : $this->applications->enrichmentStates($ids);
    }
}
