<?php

declare(strict_types=1);

namespace App\JobPosition\Infrastructure\Persistence;

use App\JobPosition\Application\Read\JobPositionReadModel;
use App\JobPosition\Application\Read\JobPositionView;
use App\JobPosition\Domain\JobPosition;
use App\Shared\Domain\Uuid;
use App\Shared\Infrastructure\Persistence\UuidType;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\Binary;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;

/**
 * Reads raw documents straight from the collection: no hydration, no identity map.
 */
final readonly class OdmJobPositionReadModel implements JobPositionReadModel
{
    public function __construct(private DocumentManager $documentManager)
    {
    }

    public function byId(string $id): ?JobPositionView
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $document = $this->collection()->findOne(['_id' => UuidType::toBinary($id)]);

        return null === $document ? null : self::view($document);
    }

    public function byIds(array $ids): array
    {
        $binaries = array_values(array_map(UuidType::toBinary(...), array_filter(array_unique($ids), Uuid::isValid(...))));

        if ([] === $binaries) {
            return [];
        }

        $views = [];
        foreach ($this->collection()->find(['_id' => ['$in' => $binaries]]) as $document) {
            $view = self::view($document);
            $views[$view->id] = $view;
        }

        return $views;
    }

    public function list(bool $onlyOpen): array
    {
        $filter = $onlyOpen ? ['status' => 'open'] : [];

        // UUID v7 ids start with their creation time, so sorting by _id is newest first.
        $cursor = $this->collection()->find($filter, ['sort' => ['_id' => -1]]);

        $views = [];
        foreach ($cursor as $document) {
            $views[] = self::view($document);
        }

        return $views;
    }

    private function collection(): Collection
    {
        return $this->documentManager->getDocumentCollection(JobPosition::class);
    }

    /** @param array<string, mixed>|object $document */
    private static function view(array|object $document): JobPositionView
    {
        $document = (array) $document;
        \assert($document['_id'] instanceof Binary && $document['createdAt'] instanceof UTCDateTime);

        return new JobPositionView(
            id: UuidType::fromBinary($document['_id']),
            title: (string) $document['title'],
            description: (string) $document['description'],
            workMode: (string) $document['workMode'],
            location: (string) $document['location'],
            status: (string) $document['status'],
            createdAt: \DateTimeImmutable::createFromInterface($document['createdAt']->toDateTime()),
        );
    }
}
