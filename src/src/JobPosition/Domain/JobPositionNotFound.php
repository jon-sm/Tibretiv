<?php

declare(strict_types=1);

namespace App\JobPosition\Domain;

final class JobPositionNotFound extends \DomainException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Position "%s" does not exist.', $id));
    }
}
