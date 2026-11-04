<?php

declare(strict_types=1);

namespace App\CandidateApplication\Domain;

final class CandidateApplicationNotFound extends \DomainException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Application "%s" does not exist.', $id));
    }
}
