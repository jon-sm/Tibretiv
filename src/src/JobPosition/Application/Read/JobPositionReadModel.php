<?php

declare(strict_types=1);

namespace App\JobPosition\Application\Read;

interface JobPositionReadModel
{
    public function byId(string $id): ?JobPositionView;

    /**
     * @param list<string> $ids
     *
     * @return array<string, JobPositionView> indexed by id; unknown ids are left out
     */
    public function byIds(array $ids): array;

    /** @return list<JobPositionView> newest first */
    public function list(bool $onlyOpen): array;
}
