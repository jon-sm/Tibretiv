<?php

declare(strict_types=1);

namespace App\JobPosition\Application\ListJobPositions;

use App\JobPosition\Application\Read\JobPositionReadModel;
use App\JobPosition\Application\Read\JobPositionView;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class ListJobPositionsHandler implements QueryHandler
{
    public function __construct(private JobPositionReadModel $positions)
    {
    }

    /** @return list<JobPositionView> */
    public function __invoke(ListJobPositions $query): array
    {
        return $this->positions->list($query->onlyOpen);
    }
}
