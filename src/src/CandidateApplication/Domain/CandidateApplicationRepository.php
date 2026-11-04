<?php

declare(strict_types=1);

namespace App\CandidateApplication\Domain;

interface CandidateApplicationRepository
{
    public function nextIdentity(): CandidateApplicationId;

    public function find(CandidateApplicationId $id): ?CandidateApplication;

    public function existsForPosition(JobPositionId $positionId, string $emailCanonical): bool;

    /**
     * @throws AlreadyApplied when another application for the same position and canonical
     *                        email was saved in the meantime
     */
    public function save(CandidateApplication $application): void;
}
