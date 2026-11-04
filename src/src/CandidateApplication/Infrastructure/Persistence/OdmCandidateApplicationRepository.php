<?php

declare(strict_types=1);

namespace App\CandidateApplication\Infrastructure\Persistence;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use App\CandidateApplication\Domain\AlreadyApplied;
use App\CandidateApplication\Domain\CandidateApplication;
use App\CandidateApplication\Domain\CandidateApplicationId;
use App\CandidateApplication\Domain\CandidateApplicationRepository;
use App\CandidateApplication\Domain\JobPositionId;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\Driver\Exception\ServerException;
use Symfony\Component\Uid\Uuid;

#[AsAlias(CandidateApplicationRepository::class)]
final readonly class OdmCandidateApplicationRepository implements CandidateApplicationRepository
{
    private const int DUPLICATE_KEY = 11000;

    public function __construct(private DocumentManager $documentManager)
    {
    }

    public function nextIdentity(): CandidateApplicationId
    {
        return new CandidateApplicationId(Uuid::v7()->toRfc4122());
    }

    public function find(CandidateApplicationId $id): ?CandidateApplication
    {
        return $this->documentManager->find(CandidateApplication::class, $id);
    }

    public function existsForPosition(JobPositionId $positionId, string $emailCanonical): bool
    {
        return null !== $this->documentManager->createQueryBuilder(CandidateApplication::class)
            ->field('positionId')->equals($positionId)
            ->field('candidate.emailCanonical')->equals($emailCanonical)
            ->select('_id')
            ->hydrate(false)
            ->getQuery()
            ->getSingleResult();
    }

    public function save(CandidateApplication $application): void
    {
        $this->documentManager->persist($application);

        try {
            $this->documentManager->flush();
        } catch (ServerException $exception) {
            if (self::DUPLICATE_KEY !== $exception->getCode()) {
                throw $exception;
            }

            // A simultaneous submission won the race to the unique index.
            $this->documentManager->detach($application);

            throw AlreadyApplied::toPosition($application->positionId());
        }
    }
}
