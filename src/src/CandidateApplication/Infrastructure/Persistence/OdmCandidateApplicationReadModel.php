<?php

declare(strict_types=1);

namespace App\CandidateApplication\Infrastructure\Persistence;

use App\CandidateApplication\Application\Read\CandidateApplicationCriteria;
use App\CandidateApplication\Application\Read\CandidateApplicationReadModel;
use App\CandidateApplication\Application\Read\CandidateApplicationView;
use App\CandidateApplication\Application\Read\EnrichmentState;
use App\CandidateApplication\Domain\CandidateApplication;
use App\Shared\Domain\Uuid;
use App\Shared\Infrastructure\Persistence\UuidType;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;

/**
 * Reads raw documents straight from the collection: no hydration, no identity map.
 */
final readonly class OdmCandidateApplicationReadModel implements CandidateApplicationReadModel
{
    private const array SEARCHED_FIELDS = ['candidate.name', 'candidate.surname', 'candidate.email', 'candidate.emailCanonical'];

    public function __construct(private DocumentManager $documentManager)
    {
    }

    public function count(CandidateApplicationCriteria $criteria): int
    {
        return $this->collection()->countDocuments(self::filter($criteria));
    }

    public function search(CandidateApplicationCriteria $criteria, int $offset, int $limit): array
    {
        $cursor = $this->collection()->find(self::filter($criteria), [
            'sort' => ['appliedAt' => -1, '_id' => -1],
            'skip' => $offset,
            'limit' => $limit,
        ]);

        $views = [];
        foreach ($cursor as $document) {
            $views[] = self::view((array) $document);
        }

        return $views;
    }

    public function byId(string $id): ?CandidateApplicationView
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $document = $this->collection()->findOne(['_id' => UuidType::toBinary($id)]);

        return null === $document ? null : self::view((array) $document);
    }

    public function enrichmentStates(array $ids): array
    {
        $binaries = array_values(array_map(UuidType::toBinary(...), array_filter($ids, Uuid::isValid(...))));

        if ([] === $binaries) {
            return [];
        }

        $cursor = $this->collection()->find(['_id' => ['$in' => $binaries]], ['projection' => ['enrichment' => 1]]);

        $states = [];
        foreach ($cursor as $document) {
            $document = (array) $document;
            \assert($document['_id'] instanceof Binary);
            $enrichment = (array) $document['enrichment'];

            $states[] = new EnrichmentState(
                UuidType::fromBinary($document['_id']),
                (string) $enrichment['status'],
                self::nullableString($enrichment['summary'] ?? null),
                self::nullableInt($enrichment['score'] ?? null),
                self::nullableDate($enrichment['enrichedAt'] ?? null),
            );
        }

        return $states;
    }

    /** @return array<string, mixed> */
    private static function filter(CandidateApplicationCriteria $criteria): array
    {
        $and = [];

        foreach (preg_split('/\s+/', $criteria->search ?? '', -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
            $regex = new Regex(preg_quote(mb_strtolower($term), '/'), 'i');
            $and[] = ['$or' => array_map(static fn (string $field): array => [$field => $regex], self::SEARCHED_FIELDS)];
        }

        if (null !== $criteria->positionId) {
            $and[] = Uuid::isValid($criteria->positionId)
                ? ['positionId' => UuidType::toBinary($criteria->positionId)]
                : ['_id' => ['$in' => []]]; // Not a UUID, so it matches no position.
        }

        if (null !== $criteria->status) {
            $and[] = ['status' => $criteria->status];
        }

        if (null !== $criteria->enrichmentStatus) {
            $and[] = ['enrichment.status' => $criteria->enrichmentStatus];
        }

        if (null !== $criteria->minScore) {
            $and[] = ['enrichment.status' => 'completed', 'enrichment.score' => ['$gte' => $criteria->minScore]];
        }

        if (null !== $criteria->appliedFrom) {
            $and[] = ['appliedAt' => ['$gte' => new UTCDateTime($criteria->appliedFrom)]];
        }

        if (null !== $criteria->appliedBefore) {
            $and[] = ['appliedAt' => ['$lt' => new UTCDateTime($criteria->appliedBefore)]];
        }

        return [] === $and ? [] : ['$and' => $and];
    }

    /** @param array<string, mixed> $document */
    private static function view(array $document): CandidateApplicationView
    {
        $candidate = (array) $document['candidate'];
        $enrichment = (array) $document['enrichment'];
        \assert($document['_id'] instanceof Binary && $document['positionId'] instanceof Binary);

        return new CandidateApplicationView(
            id: UuidType::fromBinary($document['_id']),
            positionId: UuidType::fromBinary($document['positionId']),
            name: (string) $candidate['name'],
            surname: (string) $candidate['surname'],
            email: (string) $candidate['email'],
            phone: (string) $candidate['phone'],
            notes: self::nullableString($document['notes'] ?? null),
            cv: (string) $document['cv'],
            status: (string) $document['status'],
            enrichmentStatus: (string) $enrichment['status'],
            summary: self::nullableString($enrichment['summary'] ?? null),
            score: self::nullableInt($enrichment['score'] ?? null),
            appliedAt: self::date($document['appliedAt']),
            enrichedAt: self::nullableDate($enrichment['enrichedAt'] ?? null),
            updatedAt: self::date($document['updatedAt']),
        );
    }

    private static function date(mixed $value): \DateTimeImmutable
    {
        \assert($value instanceof UTCDateTime);

        return \DateTimeImmutable::createFromInterface($value->toDateTime());
    }

    private static function nullableDate(mixed $value): ?\DateTimeImmutable
    {
        return null === $value ? null : self::date($value);
    }

    private static function nullableString(mixed $value): ?string
    {
        return null === $value ? null : (string) $value;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return \is_int($value) ? $value : null;
    }

    private function collection(): Collection
    {
        return $this->documentManager->getDocumentCollection(CandidateApplication::class);
    }
}
