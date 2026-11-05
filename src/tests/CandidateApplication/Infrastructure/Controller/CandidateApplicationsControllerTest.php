<?php

declare(strict_types=1);

namespace App\Tests\CandidateApplication\Infrastructure\Controller;

use App\CandidateApplication\Domain\CandidateApplication;
use App\Shared\Infrastructure\Persistence\UuidType;
use App\Tests\Support\DatabaseTestCase;
use MongoDB\BSON\Binary;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class CandidateApplicationsControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->resetDatabase();
        $this->seedPositions();
    }

    public function testTheListPageIsAShellForTheApi(): void
    {
        $this->submit();

        $crawler = $this->client->request('GET', '/backoffice?status=received');

        self::assertResponseIsSuccessful();
        $component = $crawler->filter('[x-data="applicationsList"]');
        self::assertCount(1, $component);
        self::assertSame('/api/applications', $component->attr('data-api-url'));
        self::assertSame('/backoffice/__id__', $component->attr('data-detail-url'));
        // Rows come from the API, not from the page
        self::assertSelectorNotExists('tbody > tr');
        self::assertStringNotContainsString('Jane', (string) $this->client->getResponse()->getContent());
    }

    public function testTheFiltersOfferTheOpenAndClosedPositions(): void
    {
        $crawler = $this->client->request('GET', '/backoffice');

        $positions = $crawler->filter('select[name="position"] option')->each(static fn ($option): string => $option->text());
        self::assertEqualsCanonicalizing(['All positions', 'Senior Backend Engineer', 'Product Designer', 'QA Engineer'], $positions);
        foreach (['q', 'status', 'enrichment', 'minScore', 'from', 'to'] as $name) {
            self::assertSelectorExists("form[role=search] [name=\"$name\"]");
        }
    }

    public function testTheDetailShowsTheApplication(): void
    {
        $this->submit(email: 'jane.doe+jobs@gmail.com', notes: 'Available from January.');

        $this->client->request('GET', '/backoffice/'.$this->applicationId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Jane Doe');
        self::assertSelectorTextContains('#candidate', 'jane.doe+jobs@gmail.com');
        self::assertSelectorTextNotContains('#candidate', 'janedoe@gmail.com');
        self::assertSelectorTextContains('#candidate', 'Available from January.');
        self::assertSelectorTextContains('#enrichment', 'Analysing the CV');
        self::assertSelectorTextContains('#timeline', '4 Nov 2026, 11:00');
        self::assertSelectorTextContains('#cv', 'Ten years building backend systems in PHP.');
    }

    public function testAnUnknownApplicationIsNotFound(): void
    {
        $this->client->request('GET', '/backoffice/01a1a675-0000-7000-8000-000000000000');

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheStatusFormSavesAndKeepsTheListFilters(): void
    {
        $this->submit();
        $id = $this->applicationId();

        $crawler = $this->client->request('GET', "/backoffice/$id?status=received&page=2");
        $this->client->submit($crawler->selectButton('Save')->form(), ['change_status[status]' => 'contacted']);

        self::assertResponseRedirects("/backoffice/$id?status=received&page=2");
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role=status]', 'Status updated');
        self::assertSame('contacted', ((array) $this->collection(CandidateApplication::class)->findOne())['status']);
    }

    public function testTheStatusCanBeSavedAsJson(): void
    {
        $this->submit();
        $id = $this->applicationId();
        $crawler = $this->client->request('GET', "/backoffice/$id");
        $form = $crawler->selectButton('Save')->form(['change_status[status]' => 'hired']);

        $this->client->request('POST', $form->getUri(), $form->getPhpValues(), server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'hired'], json_decode((string) $this->client->getResponse()->getContent(), true));
    }

    public function testAStatusChangeWithoutAValidTokenIsRefused(): void
    {
        $this->submit();
        $id = $this->applicationId();

        $this->client->request('POST', "/backoffice/$id/status", ['change_status' => ['status' => 'hired', '_token' => 'forged']]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('received', ((array) $this->collection(CandidateApplication::class)->findOne())['status']);
    }

    public function testEnrichmentStatesForPolling(): void
    {
        $this->submit();
        $id = $this->applicationId();

        $this->client->request('GET', '/backoffice/enrichment', ['ids' => [$id, 'not-a-uuid']]);

        self::assertResponseIsSuccessful();
        self::assertSame(
            [['id' => $id, 'status' => 'pending', 'score' => null, 'summary' => null, 'enrichedAt' => null]],
            json_decode((string) $this->client->getResponse()->getContent(), true),
        );
    }

    private function applicationId(): string
    {
        $document = (array) $this->collection(CandidateApplication::class)->findOne();
        \assert($document['_id'] instanceof Binary);

        return UuidType::fromBinary($document['_id']);
    }
}
