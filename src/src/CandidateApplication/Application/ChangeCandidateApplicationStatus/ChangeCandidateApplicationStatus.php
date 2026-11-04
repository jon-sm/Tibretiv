<?php

declare(strict_types=1);

namespace App\CandidateApplication\Application\ChangeCandidateApplicationStatus;

use App\Shared\Application\Bus\Command\Command;

final readonly class ChangeCandidateApplicationStatus implements Command
{
    public function __construct(
        public string $applicationId,
        /** received | contacted | rejected | hired */
        public string $status,
    ) {
    }
}
