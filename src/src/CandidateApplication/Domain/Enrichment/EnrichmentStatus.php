<?php

declare(strict_types=1);

namespace App\CandidateApplication\Domain\Enrichment;

enum EnrichmentStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
