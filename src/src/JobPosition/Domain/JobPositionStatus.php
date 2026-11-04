<?php

declare(strict_types=1);

namespace App\JobPosition\Domain;

enum JobPositionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
