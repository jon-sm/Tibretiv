<?php

declare(strict_types=1);

namespace App\CandidateApplication\Infrastructure\Persistence;

use App\CandidateApplication\Domain\CandidateApplicationId;
use App\Shared\Infrastructure\Persistence\UuidType;

final class CandidateApplicationIdType extends UuidType
{
    public const string NAME = 'candidate_application_id';

    protected function idClass(): string
    {
        return CandidateApplicationId::class;
    }
}
