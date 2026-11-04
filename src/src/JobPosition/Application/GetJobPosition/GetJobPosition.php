<?php

declare(strict_types=1);

namespace App\JobPosition\Application\GetJobPosition;

use App\Shared\Application\Bus\Query\Query;

/** Answered with a JobPositionView, or JobPositionNotFound. */
final readonly class GetJobPosition implements Query
{
    public function __construct(public string $id)
    {
    }
}
