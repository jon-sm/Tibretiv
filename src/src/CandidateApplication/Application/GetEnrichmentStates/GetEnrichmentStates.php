<?php

declare(strict_types=1);

namespace App\CandidateApplication\Application\GetEnrichmentStates;

use App\Shared\Application\Bus\Query\Query;

/**
 * Answered with a list<EnrichmentState>. Used to poll while enrichments are pending.
 * Unknown ids are left out.
 */
final readonly class GetEnrichmentStates implements Query
{
    public const int MAX_IDS = 100;

    /** @param list<string> $applicationIds */
    public function __construct(public array $applicationIds)
    {
    }
}
