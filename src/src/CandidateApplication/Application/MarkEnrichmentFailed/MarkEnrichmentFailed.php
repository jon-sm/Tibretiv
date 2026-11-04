<?php

declare(strict_types=1);

namespace App\CandidateApplication\Application\MarkEnrichmentFailed;

use App\Shared\Application\Bus\Command\Command;

/**
 * Sent once the enrichment has run out of retries.
 */
final readonly class MarkEnrichmentFailed implements Command
{
    public function __construct(public string $applicationId)
    {
    }
}
