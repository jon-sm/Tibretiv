<?php

declare(strict_types=1);

namespace App\Tests\CandidateApplication\Application;

use App\CandidateApplication\Domain\AlreadyApplied;
use App\CandidateApplication\Domain\CandidateApplication;
use App\CandidateApplication\Domain\Candidate\Candidate;
use App\CandidateApplication\Domain\Candidate\EmailAddress;
use App\CandidateApplication\Domain\Event\CandidateApplicationSubmitted;
use App\CandidateApplication\Domain\JobPositionId;
use App\CandidateApplication\Domain\JobPositionNotAcceptingApplications;
use App\CandidateApplication\Infrastructure\Persistence\OdmCandidateApplicationRepository;
use App\Tests\Support\DatabaseTestCase;
use MongoDB\BSON\Binary;
use MongoDB\BSON\UTCDateTime;

final class SubmitCandidateApplicationTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        $this->resetDatabase();
        $this->seedPositions();
    }

    public function testItStoresTheApplicationWithAppliedAtAndDefaultStatuses(): void
    {
        $this->submit(email: ' Jane.Doe+jobs@GoogleMail.com ', notes: 'Available from January.');

        $documents = $this->collection(CandidateApplication::class)->find()->toArray();
        self::assertCount(1, $documents);
        $document = (array) $documents[0];
        $candidate = (array) $document['candidate'];

        self::assertInstanceOf(Binary::class, $document['_id']);
        self::assertSame(Binary::TYPE_UUID, $document['_id']->getType());
        self::assertSame('received', $document['status']);
        self::assertSame(['status' => 'pending', 'summary' => null, 'score' => null, 'enrichedAt' => null], (array) $document['enrichment']);
        self::assertEquals(new UTCDateTime(new \DateTimeImmutable('2026-11-04 10:00:00 UTC')), $document['appliedAt']);
        self::assertEquals($document['appliedAt'], $document['updatedAt']);
        self::assertSame('jane.doe+jobs@googlemail.com', $candidate['email']);
        self::assertSame('janedoe@gmail.com', $candidate['emailCanonical']);
        self::assertSame('Available from January.', $document['notes']);
    }

    public function testItEnqueuesTheEnrichment(): void
    {
        $this->submit();

        $sent = $this->asyncTransport()->getSent();
        self::assertCount(1, $sent);
        $event = $sent[0]->getMessage();
        self::assertInstanceOf(CandidateApplicationSubmitted::class, $event);
        self::assertSame(self::OPEN_POSITION, $event->positionId);
    }

    public function testTheSameMailboxCannotApplyTwiceToAPosition(): void
    {
        $this->submit(email: 'jane.doe@gmail.com');

        $this->expectException(AlreadyApplied::class);
        $this->submit(email: 'janedoe+again@googlemail.com');
    }

    public function testTheSameEmailCanApplyToAnotherPosition(): void
    {
        $this->submit();
        $this->submit(positionId: self::OTHER_POSITION);

        self::assertSame(2, $this->collection(CandidateApplication::class)->countDocuments());
    }

    public function testAClosedPositionRejectsApplications(): void
    {
        $this->expectException(JobPositionNotAcceptingApplications::class);

        $this->submit(positionId: self::CLOSED_POSITION);
    }

    public function testAnUnknownPositionRejectsApplications(): void
    {
        $this->expectException(JobPositionNotAcceptingApplications::class);

        $this->submit(positionId: '01a1a675-0000-7000-8000-000000000000');
    }

    public function testTheUniqueIndexStopsASimultaneousDuplicate(): void
    {
        $repository = self::getContainer()->get(OdmCandidateApplicationRepository::class);
        \assert($repository instanceof OdmCandidateApplicationRepository);
        $save = fn () => $repository->save(CandidateApplication::submit(
            $repository->nextIdentity(),
            new JobPositionId(self::OPEN_POSITION),
            new Candidate('Jane', 'Doe', new EmailAddress('jane.doe@gmail.com'), '600000000'),
            null,
            'CV',
            new \DateTimeImmutable(),
        ));

        // Both passed the existence check: the second save hits the index.
        $save();
        $this->expectException(AlreadyApplied::class);
        $save();
    }
}
