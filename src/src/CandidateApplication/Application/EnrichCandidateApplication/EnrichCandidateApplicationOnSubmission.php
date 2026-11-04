<?php

declare(strict_types=1);

namespace App\CandidateApplication\Application\EnrichCandidateApplication;

use App\CandidateApplication\Application\Port\CvAnalyser;
use App\CandidateApplication\Application\Port\CvAnalysisFailed;
use App\CandidateApplication\Application\Port\JobPositionCatalog;
use App\CandidateApplication\Domain\CandidateApplicationId;
use App\CandidateApplication\Domain\CandidateApplicationRepository;
use App\CandidateApplication\Domain\Event\CandidateApplicationSubmitted;
use App\Shared\Application\Bus\Event\EventHandler;
use App\Shared\Domain\Clock\Clock;

/**
 * Runs in the worker. A thrown exception makes the message be retried; once the retries run
 * out, MarkEnrichmentFailed marks the enrichment as failed.
 */
final readonly class EnrichCandidateApplicationOnSubmission implements EventHandler
{
    public function __construct(
        private CandidateApplicationRepository $applications,
        private JobPositionCatalog $positions,
        private CvAnalyser $analyser,
        private Clock $clock,
    ) {
    }

    /** @throws CvAnalysisFailed */
    public function __invoke(CandidateApplicationSubmitted $event): void
    {
        $application = $this->applications->find(new CandidateApplicationId($event->applicationId));

        // Redelivered after a successful attempt: nothing left to do.
        if (null === $application || !$application->enrichment()->isPending()) {
            return;
        }

        $position = $this->positions->find($application->positionId()->value)
            ?? throw new CvAnalysisFailed(\sprintf('Position "%s" of application "%s" does not exist.', $application->positionId(), $application->id()));

        $analysis = $this->analyser->analyse($application->cv(), $position);

        $application->completeEnrichment($analysis->summary, $analysis->score, $this->clock->now());
        $this->applications->save($application);
    }
}
