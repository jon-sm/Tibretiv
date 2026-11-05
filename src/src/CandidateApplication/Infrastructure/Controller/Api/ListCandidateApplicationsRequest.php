<?php

declare(strict_types=1);

namespace App\CandidateApplication\Infrastructure\Controller\Api;

use App\CandidateApplication\Application\ListCandidateApplications\ListCandidateApplications;
use App\CandidateApplication\Domain\CandidateApplicationStatus;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The query string of GET /api/applications, as sent: every value is a string, or absent.
 * An empty value counts as absent. Unknown parameters are ignored.
 */
final class ListCandidateApplicationsRequest
{
    /** URL value of the enrichment filter => stored enrichment status */
    public const array ENRICHMENT_FILTER = ['processed' => 'completed', 'pending' => 'pending', 'failed' => 'failed'];

    /** Names of the parameters, as the API and the filters form use them. */
    public const array PARAMETERS = ['q', 'position', 'status', 'enrichment', 'minScore', 'from', 'to', 'page'];

    #[Assert\Sequentially([new Assert\Type('string'), new Assert\Length(max: 200)])]
    public mixed $q = null;

    #[Assert\Sequentially([new Assert\Type('string'), new Assert\Uuid(message: 'This is not a valid position.')])]
    public mixed $position = null;

    #[Assert\Sequentially([new Assert\Type('string'), new Assert\Choice(callback: [self::class, 'statuses'])])]
    public mixed $status = null;

    #[Assert\Sequentially([new Assert\Type('string'), new Assert\Choice(choices: ['processed', 'pending', 'failed'])])]
    public mixed $enrichment = null;

    #[Assert\Sequentially([
        new Assert\Type('string'),
        new Assert\Regex('/^\d{1,3}$/', message: 'This value should be a whole number.'),
        new Assert\Range(min: 0, max: 100),
    ])]
    public mixed $minScore = null;

    #[Assert\Sequentially([new Assert\Type('string'), new Assert\Date()])]
    public mixed $from = null;

    #[Assert\Sequentially([new Assert\Type('string'), new Assert\Date()])]
    public mixed $to = null;

    #[Assert\Sequentially([
        new Assert\Type('string'),
        new Assert\Regex('/^\d{1,6}$/', message: 'This value should be a whole number.'),
        new Assert\Positive(),
    ])]
    public mixed $page = null;

    /** @param array<mixed> $query */
    public static function fromQuery(array $query): self
    {
        $request = new self();
        foreach (self::PARAMETERS as $name) {
            $value = $query[$name] ?? null;
            $request->{$name} = '' === $value ? null : $value;
        }

        return $request;
    }

    /** @return list<string> */
    public static function statuses(): array
    {
        return array_map(static fn (CandidateApplicationStatus $status): string => $status->value, CandidateApplicationStatus::cases());
    }

    /** Only once validated: the values are then strings or null. */
    public function toQuery(): ListCandidateApplications
    {
        return new ListCandidateApplications(
            search: self::string($this->q),
            positionId: self::string($this->position),
            status: self::string($this->status),
            enrichmentStatus: self::ENRICHMENT_FILTER[self::string($this->enrichment) ?? ''] ?? null,
            minScore: null === $this->minScore ? null : (int) self::string($this->minScore),
            appliedFrom: self::string($this->from),
            appliedTo: self::string($this->to),
            page: null === $this->page ? 1 : (int) self::string($this->page),
        );
    }

    private static function string(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }
}
