<?php

declare(strict_types=1);

namespace App\CandidateApplication\Infrastructure\Ai;

use App\CandidateApplication\Application\Port\CvAnalyser;
use App\CandidateApplication\Application\Port\CvAnalysis;
use App\CandidateApplication\Application\Port\CvAnalysisFailed;
use App\CandidateApplication\Application\Port\JobPositionInfo;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Stands in for an LLM: no real API is called.
 *
 * Stub until the scoring criteria are agreed: the summary is the start of the CV and the score
 * is fixed. A CV containing FAILURE_TRIGGER fails every attempt, to demonstrate the failed path.
 */
final readonly class MockCvAnalyser implements CvAnalyser
{
    public const string FAILURE_TRIGGER = 'FAIL_ENRICHMENT';
    public const int SCORE_MIN = 0;
    public const int SCORE_MAX = 100;
    private const int SUMMARY_LENGTH = 280;

    public function __construct(
        /** Simulated response time, so the pending state can be seen in the UI. */
        #[Autowire(env: 'int:MOCK_LLM_LATENCY_MS')]
        private int $latencyMs = 0,
    ) {
    }

    public function analyse(string $cv, JobPositionInfo $position): CvAnalysis
    {
        if ($this->latencyMs > 0) {
            usleep($this->latencyMs * 1000);
        }

        if (str_contains($cv, self::FAILURE_TRIGGER)) {
            throw new CvAnalysisFailed('The mocked LLM was asked to fail.');
        }

        return new CvAnalysis(self::summarise($cv), rand(self::SCORE_MIN, self::SCORE_MAX));
    }

    private static function summarise(string $cv): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $cv));

        if (mb_strlen($text) <= self::SUMMARY_LENGTH) {
            return $text;
        }

        $cut = mb_substr($text, 0, self::SUMMARY_LENGTH);
        $lastSpace = mb_strrpos($cut, ' ');

        return rtrim(false === $lastSpace ? $cut : mb_substr($cut, 0, $lastSpace), " ,.;:").'…';
    }
}
