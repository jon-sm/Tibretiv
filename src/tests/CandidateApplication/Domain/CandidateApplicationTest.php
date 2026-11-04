<?php

declare(strict_types=1);

namespace App\Tests\CandidateApplication\Domain;

use App\CandidateApplication\Domain\CandidateApplication;
use App\CandidateApplication\Domain\CandidateApplicationId;
use App\CandidateApplication\Domain\Candidate\Candidate;
use App\CandidateApplication\Domain\Candidate\EmailAddress;
use App\CandidateApplication\Domain\Enrichment\EnrichmentStatus;
use App\CandidateApplication\Domain\Event\CandidateApplicationStatusChanged;
use App\CandidateApplication\Domain\Event\CandidateApplicationSubmitted;
use App\CandidateApplication\Domain\CandidateApplicationStatus;
use App\CandidateApplication\Domain\JobPositionId;
use PHPUnit\Framework\TestCase;

final class CandidateApplicationTest extends TestCase
{
    private const string ID = '0193f6b0-1d2e-7f40-8a11-6c3b9e0d4a77';
    private const string POSITION = '0193f6a2-7c1e-7b3a-9d4e-2f1a8c6b5e01';

    public function testSubmittingStartsReceivedAndPendingAndRaisesAnEvent(): void
    {
        $now = new \DateTimeImmutable('2026-11-04 10:15:00');
        $application = $this->submit($now, notes: '  ');

        self::assertSame(CandidateApplicationStatus::Received, $application->status());
        self::assertSame(EnrichmentStatus::Pending, $application->enrichment()->status);
        self::assertNull($application->enrichment()->summary);
        self::assertNull($application->enrichment()->score);
        self::assertNull($application->notes(), 'blank notes are stored as null');
        self::assertEquals($now, $application->appliedAt());
        self::assertEquals($now, $application->updatedAt());

        $events = $application->pullEvents();
        self::assertEquals([new CandidateApplicationSubmitted(self::ID, self::POSITION, $now)], $events);
        self::assertSame([], $application->pullEvents(), 'events are pulled once');
    }

    public function testAnyStatusCanChangeToAnyOther(): void
    {
        $application = $this->submit();
        $application->pullEvents();
        $later = new \DateTimeImmutable('2026-11-05 09:00:00');

        $application->changeStatus(CandidateApplicationStatus::Hired, $later);
        $application->changeStatus(CandidateApplicationStatus::Received, $later);

        self::assertSame(CandidateApplicationStatus::Received, $application->status());
        self::assertEquals($later, $application->updatedAt());
        self::assertEquals([
            new CandidateApplicationStatusChanged(self::ID, 'received', 'hired', $later),
            new CandidateApplicationStatusChanged(self::ID, 'hired', 'received', $later),
        ], $application->pullEvents());
    }

    public function testChangingToTheSameStatusDoesNothing(): void
    {
        $application = $this->submit();
        $application->pullEvents();

        $application->changeStatus(CandidateApplicationStatus::Received, new \DateTimeImmutable('2026-11-05'));

        self::assertSame([], $application->pullEvents());
        self::assertEquals(new \DateTimeImmutable('2026-11-04 10:00:00'), $application->updatedAt());
    }

    public function testCompletingTheEnrichmentStoresSummaryAndScoreOnce(): void
    {
        $application = $this->submit();
        $at = new \DateTimeImmutable('2026-11-04 10:00:05');

        $application->completeEnrichment('Backend engineer.', 87, $at);
        $application->completeEnrichment('Ignored.', 10, new \DateTimeImmutable('2026-11-06'));
        $application->failEnrichment(new \DateTimeImmutable('2026-11-06'));

        self::assertSame(EnrichmentStatus::Completed, $application->enrichment()->status);
        self::assertSame('Backend engineer.', $application->enrichment()->summary);
        self::assertSame(87, $application->enrichment()->score);
        self::assertEquals($at, $application->enrichment()->enrichedAt);
        self::assertEquals($at, $application->updatedAt());
    }

    public function testFailingTheEnrichmentLeavesNoResult(): void
    {
        $application = $this->submit();

        $application->failEnrichment(new \DateTimeImmutable('2026-11-04 10:01:00'));

        self::assertSame(EnrichmentStatus::Failed, $application->enrichment()->status);
        self::assertNull($application->enrichment()->score);
        self::assertFalse($application->enrichment()->isPending());
    }

    public function testTheScoreMustBeBetween0And100(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->submit()->completeEnrichment('Summary', 101, new \DateTimeImmutable());
    }

    public function testACvIsRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->submit(cv: '   ');
    }

    private function submit(?\DateTimeImmutable $now = null, ?string $notes = null, string $cv = 'My CV'): CandidateApplication
    {
        return CandidateApplication::submit(
            new CandidateApplicationId(self::ID),
            new JobPositionId(self::POSITION),
            new Candidate('Jane', 'Doe', new EmailAddress('jane.doe@gmail.com'), '+34 600 000 000'),
            $notes,
            $cv,
            $now ?? new \DateTimeImmutable('2026-11-04 10:00:00'),
        );
    }
}
