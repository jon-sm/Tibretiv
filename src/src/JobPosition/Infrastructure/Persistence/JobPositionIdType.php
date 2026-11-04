<?php

declare(strict_types=1);

namespace App\JobPosition\Infrastructure\Persistence;

use App\JobPosition\Domain\JobPositionId;
use App\Shared\Infrastructure\Persistence\UuidType;

final class JobPositionIdType extends UuidType
{
    public const string NAME = 'job_position_id';

    protected function idClass(): string
    {
        return JobPositionId::class;
    }
}
