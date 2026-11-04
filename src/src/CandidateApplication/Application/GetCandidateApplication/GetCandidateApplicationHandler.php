<?php

declare(strict_types=1);

namespace App\CandidateApplication\Application\GetCandidateApplication;

use App\CandidateApplication\Application\Port\JobPositionCatalog;
use App\CandidateApplication\Application\Read\CandidateApplicationReadModel;
use App\CandidateApplication\Application\Read\CandidateApplicationView;
use App\CandidateApplication\Domain\CandidateApplicationNotFound;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetCandidateApplicationHandler implements QueryHandler
{
    public function __construct(
        private CandidateApplicationReadModel $applications,
        private JobPositionCatalog $positions,
    ) {
    }

    /** @throws CandidateApplicationNotFound */
    public function __invoke(GetCandidateApplication $query): CandidateApplicationView
    {
        $view = $this->applications->byId($query->id) ?? throw CandidateApplicationNotFound::withId($query->id);

        return $view->withPosition($this->positions->find($view->positionId));
    }
}
