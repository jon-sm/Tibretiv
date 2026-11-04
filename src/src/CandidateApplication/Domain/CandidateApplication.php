<?php

declare(strict_types=1);

namespace App\CandidateApplication\Domain;

use App\CandidateApplication\Domain\Candidate\Candidate;
use App\CandidateApplication\Domain\Enrichment\Enrichment;
use App\CandidateApplication\Domain\Event\CandidateApplicationStatusChanged;
use App\CandidateApplication\Domain\Event\CandidateApplicationSubmitted;
use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * A candidate's application to a position. It has two independent statuses: the hiring step,
 * changed by a recruiter, and the enrichment, completed by the AI worker.
 */
final class CandidateApplication extends AggregateRoot
{
    private function __construct(
        private readonly CandidateApplicationId $id,
        private readonly JobPositionId $positionId,
        private readonly Candidate $candidate,
        private readonly ?string $notes,
        private readonly string $cv,
        private CandidateApplicationStatus $status,
        private Enrichment $enrichment,
        private readonly \DateTimeImmutable $appliedAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function submit(
        CandidateApplicationId $id,
        JobPositionId $positionId,
        Candidate $candidate,
        ?string $notes,
        string $cv,
        \DateTimeImmutable $now,
    ): self {
        if ('' === trim($cv)) {
            throw new \InvalidArgumentException('An application needs a CV.');
        }

        $notes = null === $notes || '' === trim($notes) ? null : trim($notes);

        $application = new self($id, $positionId, $candidate, $notes, trim($cv), CandidateApplicationStatus::Received, Enrichment::pending(), $now, $now);
        $application->record(new CandidateApplicationSubmitted($id->value, $positionId->value, $now));

        return $application;
    }

    public function changeStatus(CandidateApplicationStatus $status, \DateTimeImmutable $now): void
    {
        if ($status === $this->status) {
            return;
        }

        $previous = $this->status;
        $this->status = $status;
        $this->updatedAt = $now;

        $this->record(new CandidateApplicationStatusChanged($this->id->value, $previous->value, $status->value, $now));
    }

    /** Has no effect unless the enrichment is still pending, so a redelivered message is harmless. */
    public function completeEnrichment(string $summary, int $score, \DateTimeImmutable $now): void
    {
        if (!$this->enrichment->isPending()) {
            return;
        }

        $this->enrichment = Enrichment::completed($summary, $score, $now);
        $this->updatedAt = $now;
    }

    /** Has no effect unless the enrichment is still pending. */
    public function failEnrichment(\DateTimeImmutable $now): void
    {
        if (!$this->enrichment->isPending()) {
            return;
        }

        $this->enrichment = Enrichment::failed();
        $this->updatedAt = $now;
    }

    public function id(): CandidateApplicationId
    {
        return $this->id;
    }

    public function positionId(): JobPositionId
    {
        return $this->positionId;
    }

    public function candidate(): Candidate
    {
        return $this->candidate;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function cv(): string
    {
        return $this->cv;
    }

    public function status(): CandidateApplicationStatus
    {
        return $this->status;
    }

    public function enrichment(): Enrichment
    {
        return $this->enrichment;
    }

    public function appliedAt(): \DateTimeImmutable
    {
        return $this->appliedAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
