<?php

declare(strict_types=1);

namespace App\CandidateApplication\Application\ListCandidateApplications;

use App\CandidateApplication\Application\Port\JobPositionCatalog;
use App\CandidateApplication\Application\Read\CandidateApplicationCriteria;
use App\CandidateApplication\Application\Read\CandidateApplicationReadModel;
use App\CandidateApplication\Application\Read\CandidateApplicationsPage;
use App\CandidateApplication\Application\Read\CandidateApplicationView;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class ListCandidateApplicationsHandler implements QueryHandler
{
    /** Date filters are whole days here; appliedAt is stored in UTC. */
    public const string TIMEZONE = 'Europe/Madrid';

    public function __construct(
        private CandidateApplicationReadModel $applications,
        private JobPositionCatalog $positions,
    ) {
    }

    public function __invoke(ListCandidateApplications $query): CandidateApplicationsPage
    {
        $search = null === $query->search ? null : trim($query->search);

        $criteria = new CandidateApplicationCriteria(
            search: '' === $search ? null : $search,
            positionId: $query->positionId,
            status: $query->status,
            enrichmentStatus: $query->enrichmentStatus,
            minScore: $query->minScore,
            appliedFrom: self::startOfDay($query->appliedFrom),
            appliedBefore: self::startOfDay($query->appliedTo)?->modify('+1 day'),
        );

        $total = $this->applications->count($criteria);
        $page = max(1, min($query->page, (int) ceil($total / ListCandidateApplications::PER_PAGE)));
        $views = $this->applications->search($criteria, ($page - 1) * ListCandidateApplications::PER_PAGE, ListCandidateApplications::PER_PAGE);

        // Two reads, no join: the page, then the positions it references.
        $positions = $this->positions->findMany(array_values(array_unique(array_map(
            static fn (CandidateApplicationView $view): string => $view->positionId,
            $views,
        ))));

        return new CandidateApplicationsPage(
            array_map(static fn (CandidateApplicationView $view): CandidateApplicationView => $view->withPosition($positions[$view->positionId] ?? null), $views),
            $total,
            $page,
            ListCandidateApplications::PER_PAGE,
        );
    }

    /** The start of a Y-m-d day in the business timezone, as a UTC instant. */
    private static function startOfDay(?string $date): ?\DateTimeImmutable
    {
        if (null === $date) {
            return null;
        }

        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone(self::TIMEZONE));

        if (false === $day || $day->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a Y-m-d date.', $date));
        }

        return $day->setTimezone(new \DateTimeZone('UTC'));
    }
}
