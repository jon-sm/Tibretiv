<?php

declare(strict_types=1);

namespace App\CandidateApplication\Domain;

/**
 * One application per email per position, compared on the canonical email.
 */
final class AlreadyApplied extends \DomainException
{
    public static function toPosition(JobPositionId $positionId): self
    {
        return new self(\sprintf('This email has already applied to position "%s".', $positionId));
    }
}
