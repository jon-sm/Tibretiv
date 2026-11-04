<?php

declare(strict_types=1);

namespace App\CandidateApplication\Domain;

/**
 * The hiring step, set by a recruiter. Any status can change to any other, so a mistake can be undone.
 */
enum CandidateApplicationStatus: string
{
    case Received = 'received';
    case Contacted = 'contacted';
    case Rejected = 'rejected';
    case Hired = 'hired';
}
