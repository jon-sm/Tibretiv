<?php

declare(strict_types=1);

namespace App\CandidateApplication\Domain;

final class JobPositionNotAcceptingApplications extends \DomainException
{
    public static function notFound(string $positionId): self
    {
        return new self(\sprintf('Position "%s" does not exist.', $positionId));
    }

    public static function closed(string $positionId): self
    {
        return new self(\sprintf('Position "%s" is closed.', $positionId));
    }
}
