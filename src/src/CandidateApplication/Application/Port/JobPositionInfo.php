<?php

declare(strict_types=1);

namespace App\CandidateApplication\Application\Port;

/**
 * What the CandidateApplication context needs to know about a job position.
 */
final readonly class JobPositionInfo
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        /** remote | hybrid | onsite */
        public string $workMode,
        public string $location,
        public bool $isOpen,
    ) {
    }
}
