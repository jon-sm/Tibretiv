<?php

declare(strict_types=1);

namespace App\Tests\CandidateApplication\Infrastructure\Controller\Api;

use App\CandidateApplication\Domain\CandidateApplication;
use App\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class CandidateApplicationsApiControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->resetDatabase();
        $this->seedPositions();
    }

    public function testItListsApplicationsNewestFirstWithPagination(): void
    {
        $this->submit(email: 'old@acme.com', name: 'Old');
        $this->clock()->modify('+1 hour');
        $this->submit(email: 'new@acme.com', name: 'New', positionId: self::OTHER_POSITION);

        $body = $this->get('/api/applications');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertSame(['page' => 1, 'pages' => 1, 'perPage' => 20, 'total' => 2], $body['pagination']);
        self::assertSame(['New', 'Old'], array_column(array_column($body['items'], 'candidate'), 'name'));

        $item = $body['items'][0];
        self::assertIsString($item['id']);
        self::assertSame([
            'status' => 'received',
            'candidate' => ['name' => 'New', 'surname' => 'Doe'],
            'position' => ['id' => self::OTHER_POSITION, 'title' => 'Product Designer'],
            'enrichment' => ['status' => 'pending', 'score' => null],
            'appliedAt' => '2026-11-04T11:00:00Z',
        ], array_diff_key($item, ['id' => true]));
    }

    public function testTheEmailIsNotExposed(): void
    {
        $this->submit(email: 'jane.doe+jobs@gmail.com');

        $json = (string) $this->getRaw('/api/applications');

        self::assertStringNotContainsString('jane.doe', $json);
        self::assertStringNotContainsString('janedoe', $json);
    }

    public function testFiltersComeFromTheQueryString(): void
    {
        $this->submit(email: 'jane@acme.com', name: 'Jane');
        $this->submit(email: 'luis@acme.com', name: 'Luis', positionId: self::OTHER_POSITION);
        $this->submit(email: 'ana@acme.com', name: 'Ana', positionId: self::OTHER_POSITION);
        $this->collection(CandidateApplication::class)->updateOne(
            ['candidate.email' => 'luis@acme.com'],
            ['$set' => ['enrichment.status' => 'completed', 'enrichment.score' => 80, 'enrichment.summary' => 'Summary']],
        );

        $body = $this->get('/api/applications', [
            'q' => 'LUIS', 'position' => self::OTHER_POSITION, 'status' => 'received', 'enrichment' => 'processed',
            'minScore' => '75', 'from' => '2026-11-04', 'to' => '2026-11-04', 'page' => '1',
        ]);

        self::assertSame(['Luis'], array_column(array_column($body['items'], 'candidate'), 'name'));
        self::assertSame(['status' => 'completed', 'score' => 80], $body['items'][0]['enrichment']);
    }

    public function testEmptyValuesAreIgnored(): void
    {
        $this->submit();

        $body = $this->get('/api/applications', ['q' => '', 'status' => '', 'minScore' => '', 'page' => '']);

        self::assertSame(1, $body['pagination']['total']);
    }

    public function testAPagePastTheLastOneAnswersTheLastOne(): void
    {
        $this->submit();

        $body = $this->get('/api/applications', ['page' => '9']);

        self::assertSame(1, $body['pagination']['page']);
        self::assertCount(1, $body['items']);
    }

    public function testNoResults(): void
    {
        $body = $this->get('/api/applications', ['q' => 'nobody']);

        self::assertSame([], $body['items']);
        self::assertSame(['page' => 1, 'pages' => 1, 'perPage' => 20, 'total' => 0], $body['pagination']);
    }

    /** @param array<string, mixed> $query */
    #[DataProvider('invalidFilters')]
    public function testInvalidFiltersAreRefused(array $query, string $parameter): void
    {
        $body = $this->get('/api/applications', $query);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([$parameter], array_keys($body['errors']));
        self::assertIsString($body['errors'][$parameter]);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidFilters(): iterable
    {
        yield 'search too long' => [['q' => str_repeat('a', 201)], 'q'];
        yield 'position not an id' => [['position' => 'backend'], 'position'];
        yield 'unknown status' => [['status' => 'bogus'], 'status'];
        yield 'unknown enrichment' => [['enrichment' => 'completed'], 'enrichment'];
        yield 'score above 100' => [['minScore' => '500'], 'minScore'];
        yield 'score not a number' => [['minScore' => 'high'], 'minScore'];
        yield 'score with decimals' => [['minScore' => '50.5'], 'minScore'];
        yield 'date not Y-m-d' => [['from' => 'yesterday'], 'from'];
        yield 'impossible date' => [['to' => '2026-02-30'], 'to'];
        yield 'page zero' => [['page' => '0'], 'page'];
        yield 'page not a number' => [['page' => 'two'], 'page'];
        yield 'array value' => [['status' => ['received', 'hired']], 'status'];
    }

    public function testEveryInvalidFilterIsReported(): void
    {
        $body = $this->get('/api/applications', ['status' => 'bogus', 'minScore' => '500', 'q' => 'jane']);

        self::assertResponseStatusCodeSame(422);
        self::assertEqualsCanonicalizing(['status', 'minScore'], array_keys($body['errors']));
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    private function get(string $uri, array $query = []): array
    {
        $body = json_decode((string) $this->getRaw($uri, $query), true, flags: \JSON_THROW_ON_ERROR);
        \assert(\is_array($body));

        return $body;
    }

    /** @param array<string, mixed> $query */
    private function getRaw(string $uri, array $query = []): string|false
    {
        $this->client->request('GET', $uri, $query, server: ['HTTP_ACCEPT' => 'application/json']);

        return $this->client->getResponse()->getContent();
    }
}
