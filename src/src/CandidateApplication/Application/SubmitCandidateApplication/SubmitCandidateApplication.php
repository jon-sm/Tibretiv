<?php

declare(strict_types=1);

namespace App\CandidateApplication\Application\SubmitCandidateApplication;

use App\Shared\Application\Bus\Command\Command;

final readonly class SubmitCandidateApplication implements Command
{
    public function __construct(
        public string $positionId,
        public string $name,
        public string $surname,
        public string $email,
        public string $phone,
        public string $cv,
        public ?string $notes = null,
    ) {
    }
}
