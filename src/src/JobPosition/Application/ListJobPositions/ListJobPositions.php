<?php

declare(strict_types=1);

namespace App\JobPosition\Application\ListJobPositions;

use App\Shared\Application\Bus\Query\Query;

/** Answered with a list<JobPositionView>, newest first. */
final readonly class ListJobPositions implements Query
{
    public function __construct(public bool $onlyOpen = false)
    {
    }
}
