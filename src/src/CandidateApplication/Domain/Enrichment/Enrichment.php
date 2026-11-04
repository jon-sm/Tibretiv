<?php

declare(strict_types=1);

namespace App\CandidateApplication\Domain\Enrichment;

/**
 * What the AI enrichment produced for an application. Embedded in the application.
 * Summary, score and enrichedAt stay null until it completes.
 */
final readonly class Enrichment
{
    public const int MIN_SCORE = 0;
    public const int MAX_SCORE = 100;

    private function __construct(
        public EnrichmentStatus $status,
        public ?string $summary,
        public ?int $score,
        public ?\DateTimeImmutable $enrichedAt,
    ) {
    }

    public static function pending(): self
    {
        return new self(EnrichmentStatus::Pending, null, null, null);
    }

    public static function completed(string $summary, int $score, \DateTimeImmutable $enrichedAt): self
    {
        if ('' === trim($summary)) {
            throw new \InvalidArgumentException('An enrichment summary cannot be empty.');
        }

        if ($score < self::MIN_SCORE || $score > self::MAX_SCORE) {
            throw new \InvalidArgumentException(\sprintf('A score must be between %d and %d, got %d.', self::MIN_SCORE, self::MAX_SCORE, $score));
        }

        return new self(EnrichmentStatus::Completed, trim($summary), $score, $enrichedAt);
    }

    public static function failed(): self
    {
        return new self(EnrichmentStatus::Failed, null, null, null);
    }

    public function isPending(): bool
    {
        return EnrichmentStatus::Pending === $this->status;
    }
}
