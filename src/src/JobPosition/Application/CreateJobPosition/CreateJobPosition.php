<?php

declare(strict_types=1);

namespace App\JobPosition\Application\CreateJobPosition;

use App\Shared\Application\Bus\Command\Command;

/**
 * Creates a position, or updates it when $id already exists, so seeding can run again.
 */
final readonly class CreateJobPosition implements Command
{
    public function __construct(
        public string $title,
        public string $description,
        public string $workMode,
        public string $location,
        public string $status = 'open',
        public ?string $id = null,
    ) {
    }
}
