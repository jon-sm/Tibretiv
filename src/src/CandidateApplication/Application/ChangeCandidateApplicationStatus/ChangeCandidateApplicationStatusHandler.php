<?php

declare(strict_types=1);

namespace App\CandidateApplication\Application\ChangeCandidateApplicationStatus;

use App\CandidateApplication\Domain\CandidateApplicationId;
use App\CandidateApplication\Domain\CandidateApplicationNotFound;
use App\CandidateApplication\Domain\CandidateApplicationRepository;
use App\CandidateApplication\Domain\CandidateApplicationStatus;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Bus\Event\EventBus;
use App\Shared\Domain\Clock\Clock;

final readonly class ChangeCandidateApplicationStatusHandler implements CommandHandler
{
    public function __construct(
        private CandidateApplicationRepository $applications,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    /** @throws CandidateApplicationNotFound */
    public function __invoke(ChangeCandidateApplicationStatus $command): void
    {
        $application = $this->applications->find(new CandidateApplicationId($command->applicationId))
            ?? throw CandidateApplicationNotFound::withId($command->applicationId);

        $application->changeStatus(CandidateApplicationStatus::from($command->status), $this->clock->now());

        $this->applications->save($application);
        $this->events->publish(...$application->pullEvents());
    }
}
