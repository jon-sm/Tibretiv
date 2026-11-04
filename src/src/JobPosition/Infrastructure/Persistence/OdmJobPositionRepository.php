<?php

declare(strict_types=1);

namespace App\JobPosition\Infrastructure\Persistence;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use App\JobPosition\Domain\JobPosition;
use App\JobPosition\Domain\JobPositionId;
use App\JobPosition\Domain\JobPositionRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Uid\Uuid;

#[AsAlias(JobPositionRepository::class)]
final readonly class OdmJobPositionRepository implements JobPositionRepository
{
    public function __construct(private DocumentManager $documentManager)
    {
    }

    public function nextIdentity(): JobPositionId
    {
        return new JobPositionId(Uuid::v7()->toRfc4122());
    }

    public function find(JobPositionId $id): ?JobPosition
    {
        return $this->documentManager->find(JobPosition::class, $id);
    }

    public function save(JobPosition $position): void
    {
        $this->documentManager->persist($position);
        $this->documentManager->flush();
    }
}
