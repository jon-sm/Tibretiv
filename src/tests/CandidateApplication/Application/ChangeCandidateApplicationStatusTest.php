<?php

declare(strict_types=1);

namespace App\Tests\CandidateApplication\Application;

use App\CandidateApplication\Application\ChangeCandidateApplicationStatus\ChangeCandidateApplicationStatus;
use App\CandidateApplication\Domain\CandidateApplication;
use App\CandidateApplication\Domain\CandidateApplicationNotFound;
use App\CandidateApplication\Domain\Event\CandidateApplicationStatusChanged;
use App\Shared\Infrastructure\Persistence\UuidType;
use App\Tests\Support\DatabaseTestCase;
use MongoDB\BSON\Binary;
use MongoDB\BSON\UTCDateTime;

final class ChangeCandidateApplicationStatusTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        $this->resetDatabase();
        $this->seedPositions();
    }

    public function testItSavesTheStatusUpdatesUpdatedAtAndRaisesAnEvent(): void
    {
        $this->submit();
        $id = $this->applicationId();
        $this->clock()->modify('+2 hours');

        $this->commandBus()->dispatch(new ChangeCandidateApplicationStatus($id, 'hired'));

        $document = (array) $this->collection(CandidateApplication::class)->findOne();
        self::assertSame('hired', $document['status']);
        self::assertEquals(new UTCDateTime(new \DateTimeImmutable('2026-11-04 12:00:00 UTC')), $document['updatedAt']);
        self::assertEquals(new UTCDateTime(new \DateTimeImmutable('2026-11-04 10:00:00 UTC')), $document['appliedAt']);

        $last = $this->asyncTransport()->getSent()[1]->getMessage();
        self::assertInstanceOf(CandidateApplicationStatusChanged::class, $last);
        self::assertSame(['received', 'hired'], [$last->from, $last->to]);
    }

    public function testAnUnknownApplicationIsNotFound(): void
    {
        $this->expectException(CandidateApplicationNotFound::class);

        $this->commandBus()->dispatch(new ChangeCandidateApplicationStatus('01a1a675-0000-7000-8000-000000000000', 'hired'));
    }

    private function applicationId(): string
    {
        $document = (array) $this->collection(CandidateApplication::class)->findOne();
        \assert($document['_id'] instanceof Binary);

        return UuidType::fromBinary($document['_id']);
    }
}
