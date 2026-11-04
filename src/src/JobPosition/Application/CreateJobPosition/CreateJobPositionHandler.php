<?php

declare(strict_types=1);

namespace App\JobPosition\Application\CreateJobPosition;

use App\JobPosition\Domain\JobPosition;
use App\JobPosition\Domain\JobPositionId;
use App\JobPosition\Domain\JobPositionRepository;
use App\JobPosition\Domain\JobPositionStatus;
use App\JobPosition\Domain\WorkMode;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Domain\Clock\Clock;

final readonly class CreateJobPositionHandler implements CommandHandler
{
    public function __construct(
        private JobPositionRepository $positions,
        private Clock $clock,
    ) {
    }

    public function __invoke(CreateJobPosition $command): void
    {
        $id = null === $command->id ? $this->positions->nextIdentity() : new JobPositionId($command->id);
        $workMode = WorkMode::from($command->workMode);
        $status = JobPositionStatus::from($command->status);

        $position = $this->positions->find($id);

        if (null === $position) {
            $position = JobPosition::create($id, $command->title, $command->description, $workMode, $command->location, $status, $this->clock->now());
        } else {
            $position->revise($command->title, $command->description, $workMode, $command->location, $status);
        }

        $this->positions->save($position);
    }
}
