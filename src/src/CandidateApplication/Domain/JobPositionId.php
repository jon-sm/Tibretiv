<?php

declare(strict_types=1);

namespace App\CandidateApplication\Domain;

use App\Shared\Domain\Uuid;

/**
 * A position of the JobPosition context, referenced by id only.
 */
final readonly class JobPositionId extends Uuid
{
}
