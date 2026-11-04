<?php

declare(strict_types=1);

namespace App\CandidateApplication\Application\Read;

use App\CandidateApplication\Application\Port\JobPositionInfo;

/**
 * Read model of an application, for the backoffice list and detail.
 * The canonical email is deliberately left out: it is never shown.
 */
final readonly class CandidateApplicationView
{
    public function __construct(
        public string $id,
        public string $positionId,
        public string $name,
        public string $surname,
        public string $email,
        public string $phone,
        public ?string $notes,
        public string $cv,
        /** received | contacted | rejected | hired */
        public string $status,
        /** pending | completed | failed */
        public string $enrichmentStatus,
        public ?string $summary,
        public ?int $score,
        public \DateTimeImmutable $appliedAt,
        public ?\DateTimeImmutable $enrichedAt,
        public \DateTimeImmutable $updatedAt,
        /** Null when the position could not be found, which should not happen: positions are never deleted. */
        public ?JobPositionInfo $position = null,
    ) {
    }

    public function fullName(): string
    {
        return $this->name.' '.$this->surname;
    }

    public function isEnrichmentPending(): bool
    {
        return 'pending' === $this->enrichmentStatus;
    }

    public function withPosition(?JobPositionInfo $position): self
    {
        return new self(
            $this->id, $this->positionId, $this->name, $this->surname, $this->email, $this->phone, $this->notes,
            $this->cv, $this->status, $this->enrichmentStatus, $this->summary, $this->score, $this->appliedAt,
            $this->enrichedAt, $this->updatedAt, $position,
        );
    }
}
