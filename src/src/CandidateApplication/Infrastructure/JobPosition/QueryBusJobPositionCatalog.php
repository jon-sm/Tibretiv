<?php

declare(strict_types=1);

namespace App\CandidateApplication\Infrastructure\JobPosition;

use App\CandidateApplication\Application\Port\JobPositionCatalog;
use App\CandidateApplication\Application\Port\JobPositionInfo;
use App\JobPosition\Application\GetJobPosition\GetJobPosition;
use App\JobPosition\Application\GetJobPositions\GetJobPositions;
use App\JobPosition\Application\Read\JobPositionView;
use App\JobPosition\Domain\JobPositionNotFound;
use App\Shared\Application\Bus\Query\QueryBus;

/**
 * Anti-corruption layer: reads job positions through the JobPosition context's public queries and
 * translates them into CandidateApplication's JobPositionInfo.
 */
final readonly class QueryBusJobPositionCatalog implements JobPositionCatalog
{
    public function __construct(private QueryBus $queries)
    {
    }

    public function find(string $id): ?JobPositionInfo
    {
        try {
            $view = $this->queries->ask(new GetJobPosition($id));
        } catch (JobPositionNotFound) {
            return null;
        }

        \assert($view instanceof JobPositionView);

        return self::info($view);
    }

    public function findMany(array $ids): array
    {
        /** @var array<string, JobPositionView> $views */
        $views = $this->queries->ask(new GetJobPositions($ids));

        return array_map(self::info(...), $views);
    }

    private static function info(JobPositionView $view): JobPositionInfo
    {
        return new JobPositionInfo($view->id, $view->title, $view->description, $view->workMode, $view->location, $view->isOpen());
    }
}
