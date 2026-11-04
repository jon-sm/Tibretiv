<?php

declare(strict_types=1);

namespace App\JobPosition\Application\Read;

/**
 * Read model of a position, as the views and other contexts see it.
 */
final readonly class JobPositionView
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        /** remote | hybrid | onsite */
        public string $workMode,
        public string $location,
        /** open | closed */
        public string $status,
        public \DateTimeImmutable $createdAt,
    ) {
    }

    public function isOpen(): bool
    {
        return 'open' === $this->status;
    }
}
