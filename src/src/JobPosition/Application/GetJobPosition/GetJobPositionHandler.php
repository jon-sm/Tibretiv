<?php

declare(strict_types=1);

namespace App\JobPosition\Application\GetJobPosition;

use App\JobPosition\Application\Read\JobPositionReadModel;
use App\JobPosition\Application\Read\JobPositionView;
use App\JobPosition\Domain\JobPositionNotFound;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetJobPositionHandler implements QueryHandler
{
    public function __construct(private JobPositionReadModel $positions)
    {
    }

    public function __invoke(GetJobPosition $query): JobPositionView
    {
        return $this->positions->byId($query->id) ?? throw JobPositionNotFound::withId($query->id);
    }
}
