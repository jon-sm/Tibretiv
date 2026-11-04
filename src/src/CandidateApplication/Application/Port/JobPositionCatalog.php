<?php

declare(strict_types=1);

namespace App\CandidateApplication\Application\Port;

/**
 * CandidateApplication's view of the JobPosition context's positions.
 */
interface JobPositionCatalog
{
    public function find(string $id): ?JobPositionInfo;

    /**
     * @param list<string> $ids
     *
     * @return array<string, JobPositionInfo> indexed by id; unknown ids are left out
     */
    public function findMany(array $ids): array;
}
