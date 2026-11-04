<?php

declare(strict_types=1);

namespace App\CandidateApplication\Application\MarkEnrichmentFailed;

use App\CandidateApplication\Domain\CandidateApplicationId;
use App\CandidateApplication\Domain\CandidateApplicationRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Domain\Clock\Clock;

final readonly class MarkEnrichmentFailedHandler implements CommandHandler
{
    public function __construct(
        private CandidateApplicationRepository $applications,
        private Clock $clock,
    ) {
    }

    public function __invoke(MarkEnrichmentFailed $command): void
    {
        $application = $this->applications->find(new CandidateApplicationId($command->applicationId));

        if (null === $application || !$application->enrichment()->isPending()) {
            return;
        }

        $application->failEnrichment($this->clock->now());
        $this->applications->save($application);
    }
}
