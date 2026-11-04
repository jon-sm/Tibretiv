<?php

declare(strict_types=1);

namespace App\CandidateApplication\Application\GetCandidateApplication;

use App\Shared\Application\Bus\Query\Query;

/** Answered with an CandidateApplicationView including its position, or CandidateApplicationNotFound. */
final readonly class GetCandidateApplication implements Query
{
    public function __construct(public string $id)
    {
    }
}
