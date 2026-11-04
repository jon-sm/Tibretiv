<?php

declare(strict_types=1);

namespace App\JobPosition\Application\GetJobPositions;

use App\Shared\Application\Bus\Query\Query;

/** Answered with an array<string, JobPositionView> indexed by id. Unknown ids are left out. */
final readonly class GetJobPositions implements Query
{
    /** @param list<string> $ids */
    public function __construct(public array $ids)
    {
    }
}
