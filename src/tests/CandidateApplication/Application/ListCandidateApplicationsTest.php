<?php

declare(strict_types=1);

namespace App\Tests\CandidateApplication\Application;

use App\CandidateApplication\Application\ChangeCandidateApplicationStatus\ChangeCandidateApplicationStatus;
use App\CandidateApplication\Application\ListCandidateApplications\ListCandidateApplications;
use App\CandidateApplication\Application\Read\CandidateApplicationsPage;
use App\CandidateApplication\Application\Read\CandidateApplicationView;
use App\CandidateApplication\Domain\CandidateApplication;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Tests\Support\DatabaseTestCase;

final class ListCandidateApplicationsTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        $this->resetDatabase();
        $this->seedPositions();
    }

    public function testNewestFirstWithPositionTitles(): void
    {
        $this->submitAt('2026-11-01 09:00', 'old@acme.com', 'Old');
        $this->submitAt('2026-11-03 09:00', 'new@acme.com', 'New', positionId: self::OTHER_POSITION);
        $this->submitAt('2026-11-02 09:00', 'mid@acme.com', 'Mid');

        $page = $this->list(new ListCandidateApplications());

        self::assertSame(['New', 'Mid', 'Old'], $this->names($page));
        self::assertSame(3, $page->total);
        self::assertSame('Product Designer', $page->items[0]->position?->title);
        self::assertSame('Senior Backend Engineer', $page->items[1]->position?->title);
    }

    public function testItPaginatesBy20AndClampsPastTheLastPage(): void
    {
        for ($i = 1; $i <= 25; ++$i) {
            $this->clock()->modify('+1 minute');
            $this->submit(email: "c{$i}@acme.com", name: "C{$i}");
        }

        $first = $this->list(new ListCandidateApplications());
        $second = $this->list(new ListCandidateApplications(page: 2));
        $beyond = $this->list(new ListCandidateApplications(page: 9));

        self::assertCount(20, $first->items);
        self::assertSame('C25', $first->items[0]->name);
        self::assertSame(2, $first->pages);
        self::assertCount(5, $second->items);
        self::assertSame('C5', $second->items[0]->name);
        self::assertSame(2, $beyond->page);
    }

    public function testSearchIsCaseInsensitiveOnNameSurnameAndEmail(): void
    {
        $this->submit(email: 'jane.doe@gmail.com', name: 'Jane', surname: 'Doe');
        $this->submit(email: 'luis@acme.com', name: 'Luis', surname: 'García');

        self::assertSame(['Jane'], $this->names($this->list(new ListCandidateApplications(search: 'JANE'))));
        self::assertSame(['Luis'], $this->names($this->list(new ListCandidateApplications(search: 'garcía'))));
        self::assertSame(['Luis'], $this->names($this->list(new ListCandidateApplications(search: 'acme.com'))));
        self::assertSame(['Jane'], $this->names($this->list(new ListCandidateApplications(search: 'jane doe'))), 'every word must match');
        self::assertSame([], $this->names($this->list(new ListCandidateApplications(search: 'jane garcía'))));
        self::assertSame([], $this->names($this->list(new ListCandidateApplications(search: '.*'))), 'regex characters are literal');
    }

    public function testSearchMatchesTheCanonicalEmailWithoutShowingIt(): void
    {
        $this->submit(email: 'jane.doe+jobs@gmail.com');

        $page = $this->list(new ListCandidateApplications(search: 'janedoe'));

        self::assertSame(['Jane'], $this->names($page));
        self::assertSame('jane.doe+jobs@gmail.com', $page->items[0]->email);
    }

    public function testFiltersByPositionStatusAndEnrichment(): void
    {
        $this->submit(email: 'a@acme.com', name: 'A');
        $this->submit(email: 'b@acme.com', name: 'B', positionId: self::OTHER_POSITION);
        $this->changeStatus('b@acme.com', 'contacted');
        $this->setEnrichment('a@acme.com', 'failed', null);

        self::assertSame(['B'], $this->names($this->list(new ListCandidateApplications(positionId: self::OTHER_POSITION))));
        self::assertSame(['B'], $this->names($this->list(new ListCandidateApplications(status: 'contacted'))));
        self::assertSame(['A'], $this->names($this->list(new ListCandidateApplications(enrichmentStatus: 'failed'))));
        self::assertSame(['B'], $this->names($this->list(new ListCandidateApplications(enrichmentStatus: 'pending'))));
        self::assertSame([], $this->names($this->list(new ListCandidateApplications(positionId: self::OTHER_POSITION, status: 'received'))));
        self::assertSame([], $this->names($this->list(new ListCandidateApplications(positionId: 'not-a-uuid'))));
    }

    public function testMinimumScoreOnlyMatchesScoredApplications(): void
    {
        $this->submit(email: 'high@acme.com', name: 'High');
        $this->submit(email: 'low@acme.com', name: 'Low');
        $this->submit(email: 'pending@acme.com', name: 'Pending');
        $this->setEnrichment('high@acme.com', 'completed', 80);
        $this->setEnrichment('low@acme.com', 'completed', 40);

        self::assertSame(['High'], $this->names($this->list(new ListCandidateApplications(minScore: 80))));
        self::assertEqualsCanonicalizing(['High', 'Low'], $this->names($this->list(new ListCandidateApplications(minScore: 0))));
    }

    public function testDatesAreWholeInclusiveDaysInMadrid(): void
    {
        // Madrid is UTC+1 in November.
        $this->submitAt('2026-11-03 22:59', 'before@acme.com', 'Before'); // Nov 3, 23:59 in Madrid
        $this->submitAt('2026-11-03 23:00', 'start@acme.com', 'Start');   // Nov 4, 00:00
        $this->submitAt('2026-11-04 22:59', 'end@acme.com', 'End');       // Nov 4, 23:59
        $this->submitAt('2026-11-04 23:00', 'after@acme.com', 'After');   // Nov 5, 00:00

        self::assertSame(['End', 'Start'], $this->names($this->list(new ListCandidateApplications(appliedFrom: '2026-11-04', appliedTo: '2026-11-04'))));
        self::assertSame(['After', 'End', 'Start'], $this->names($this->list(new ListCandidateApplications(appliedFrom: '2026-11-04'))));
        self::assertSame(['End', 'Start', 'Before'], $this->names($this->list(new ListCandidateApplications(appliedTo: '2026-11-04'))));
    }

    public function testPendingEnrichmentIsReportedForPolling(): void
    {
        $this->submit();

        self::assertTrue($this->list(new ListCandidateApplications())->hasPendingEnrichment());
        $this->setEnrichment('jane.doe@gmail.com', 'completed', 70);
        self::assertFalse($this->list(new ListCandidateApplications())->hasPendingEnrichment());
    }

    private function submitAt(string $utc, string $email, string $name, string $positionId = self::OPEN_POSITION): void
    {
        $this->clock()->modify($utc.' UTC');
        $this->submit(email: $email, name: $name, positionId: $positionId);
    }

    private function list(ListCandidateApplications $query): CandidateApplicationsPage
    {
        $page = self::getContainer()->get(QueryBus::class)->ask($query);
        \assert($page instanceof CandidateApplicationsPage);

        return $page;
    }

    /** @return list<string> */
    private function names(CandidateApplicationsPage $page): array
    {
        return array_map(static fn (CandidateApplicationView $view): string => $view->name, $page->items);
    }

    private function changeStatus(string $email, string $status): void
    {
        $document = (array) $this->collection(CandidateApplication::class)->findOne(['candidate.email' => $email]);
        \assert($document['_id'] instanceof \MongoDB\BSON\Binary);
        $this->commandBus()->dispatch(new ChangeCandidateApplicationStatus(\App\Shared\Infrastructure\Persistence\UuidType::fromBinary($document['_id']), $status));
    }

    private function setEnrichment(string $email, string $status, ?int $score): void
    {
        $this->collection(CandidateApplication::class)->updateOne(
            ['candidate.email' => $email],
            ['$set' => ['enrichment.status' => $status, 'enrichment.score' => $score, 'enrichment.summary' => null === $score ? null : 'Summary']],
        );
    }
}
