<?php

declare(strict_types=1);

namespace App\CandidateApplication\Infrastructure\Persistence;

use App\CandidateApplication\Domain\JobPositionId;
use App\Shared\Infrastructure\Persistence\UuidType;

final class JobPositionIdType extends UuidType
{
    public const string NAME = 'candidate_application_job_position_id';

    protected function idClass(): string
    {
        return JobPositionId::class;
    }
}
