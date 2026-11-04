<?php

declare(strict_types=1);

namespace App\JobPosition\Domain;

use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * A job opening candidates can apply to while it is open. Positions are never deleted:
 * closing one stops new applications.
 */
final class JobPosition extends AggregateRoot
{
    private function __construct(
        private readonly JobPositionId $id,
        private string $title,
        private string $description,
        private WorkMode $workMode,
        private string $location,
        private JobPositionStatus $status,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        JobPositionId $id,
        string $title,
        string $description,
        WorkMode $workMode,
        string $location,
        JobPositionStatus $status,
        \DateTimeImmutable $now,
    ): self {
        self::guard($title, $description, $location);

        return new self($id, trim($title), trim($description), $workMode, trim($location), $status, $now);
    }

    public function revise(string $title, string $description, WorkMode $workMode, string $location, JobPositionStatus $status): void
    {
        self::guard($title, $description, $location);

        $this->title = trim($title);
        $this->description = trim($description);
        $this->workMode = $workMode;
        $this->location = trim($location);
        $this->status = $status;
    }

    public function close(): void
    {
        $this->status = JobPositionStatus::Closed;
    }

    private static function guard(string $title, string $description, string $location): void
    {
        foreach (['title' => $title, 'description' => $description, 'location' => $location] as $field => $value) {
            if ('' === trim($value)) {
                throw new \InvalidArgumentException(\sprintf('A position needs a %s.', $field));
            }
        }
    }

    public function id(): JobPositionId
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function workMode(): WorkMode
    {
        return $this->workMode;
    }

    public function location(): string
    {
        return $this->location;
    }

    public function status(): JobPositionStatus
    {
        return $this->status;
    }

    public function isOpen(): bool
    {
        return JobPositionStatus::Open === $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
