<?php

declare(strict_types=1);

namespace App\CandidateApplication\Application\Port;

/**
 * The AI that summarises a CV and scores its relevance for a position.
 */
interface CvAnalyser
{
    /** @throws CvAnalysisFailed */
    public function analyse(string $cv, JobPositionInfo $position): CvAnalysis;
}
