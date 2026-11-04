<?php

declare(strict_types=1);

namespace App\JobPosition\Domain;

interface JobPositionRepository
{
    public function nextIdentity(): JobPositionId;

    public function find(JobPositionId $id): ?JobPosition;

    public function save(JobPosition $position): void;
}
