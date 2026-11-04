<?php

declare(strict_types=1);

namespace App\JobPosition\Application\GetJobPositions;

use App\JobPosition\Application\Read\JobPositionReadModel;
use App\JobPosition\Application\Read\JobPositionView;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetJobPositionsHandler implements QueryHandler
{
    public function __construct(private JobPositionReadModel $positions)
    {
    }

    /** @return array<string, JobPositionView> */
    public function __invoke(GetJobPositions $query): array
    {
        return [] === $query->ids ? [] : $this->positions->byIds($query->ids);
    }
}
