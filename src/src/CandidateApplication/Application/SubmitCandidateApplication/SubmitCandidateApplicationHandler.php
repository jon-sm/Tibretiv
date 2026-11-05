<?php

declare(strict_types=1);

namespace App\CandidateApplication\Application\SubmitCandidateApplication;

use App\CandidateApplication\Application\Port\JobPositionCatalog;
use App\CandidateApplication\Domain\AlreadyApplied;
use App\CandidateApplication\Domain\CandidateApplication;
use App\CandidateApplication\Domain\CandidateApplicationRepository;
use App\CandidateApplication\Domain\Candidate\Candidate;
use App\CandidateApplication\Domain\Candidate\EmailAddress;
use App\CandidateApplication\Domain\JobPositionId;
use App\CandidateApplication\Domain\JobPositionNotAcceptingApplications;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Bus\Event\EventBus;
use App\Shared\Domain\Clock\Clock;

final readonly class SubmitCandidateApplicationHandler implements CommandHandler
{
    public function __construct(
        private CandidateApplicationRepository $applications,
        private JobPositionCatalog $positions,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    /**
     * @throws JobPositionNotAcceptingApplications
     * @throws AlreadyApplied
     */
    public function __invoke(SubmitCandidateApplication $command): void
    {
        // validate job position exists
        $position = $this->positions->find($command->positionId)
            ?? throw JobPositionNotAcceptingApplications::notFound($command->positionId);

        if (!$position->isOpen) {
            throw JobPositionNotAcceptingApplications::closed($position->id);
        }

        $positionId = new JobPositionId($position->id);
        $email = new EmailAddress($command->email);

        // validate candidate has not already applied to this position
        if ($this->applications->existsForPosition($positionId, $email->canonical)) {
            throw AlreadyApplied::toPosition($positionId);
        }

        $application = CandidateApplication::submit(
            $this->applications->nextIdentity(),
            $positionId,
            new Candidate($command->name, $command->surname, $email, $command->phone),
            $command->notes,
            $command->cv,
            $this->clock->now(),
        );

        $this->applications->save($application);
        $this->events->publish(...$application->pullEvents());
    }
}
