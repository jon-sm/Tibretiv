<?php

declare(strict_types=1);

namespace App\Tests\CandidateApplication\Infrastructure\Controller;

use App\CandidateApplication\Domain\CandidateApplication;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class ApplyControllerTest extends DatabaseTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->resetDatabase();
        $this->seedPositions();
    }

    public function testSubmittingRedirectsToAOneTimeConfirmation(): void
    {
        $this->apply(['email' => 'Jane.Doe@gmail.com']);

        self::assertResponseRedirects('/application-received');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Thanks, Jane.');
        self::assertSelectorTextContains('main', 'Senior Backend Engineer');
        self::assertSelectorTextContains('main', 'Jane.Doe@gmail.com');
        self::assertSame(1, $this->collection(CandidateApplication::class)->countDocuments());

        $this->client->request('GET', '/application-received');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('main', 'Thanks');
    }

    public function testInvalidFieldsAreReportedAndTypedValuesKept(): void
    {
        $crawler = $this->apply(['name' => '', 'email' => 'not-an-email', 'cv' => '']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Enter your name.');
        self::assertSelectorTextContains('main', 'Enter a valid email address.');
        self::assertSelectorTextContains('main', 'Paste your CV.');
        self::assertSame('Doe', $crawler->filter('#apply_surname')->attr('value'));
        self::assertSame(0, $this->collection(CandidateApplication::class)->countDocuments());
    }

    public function testApplyingTwiceWithTheSameMailboxIsRefused(): void
    {
        $this->apply(['email' => 'jane.doe@gmail.com']);
        $this->apply(['email' => 'janedoe+again@gmail.com']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'You have already applied to this position with this email.');
        self::assertSame(1, $this->collection(CandidateApplication::class)->countDocuments());
    }

    public function testAClosedPositionShowsNoForm(): void
    {
        $this->client->request('GET', '/positions/'.self::CLOSED_POSITION);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Applications closed.');
        self::assertSelectorNotExists('form');
    }

    public function testAnUnknownPositionIsNotFound(): void
    {
        $this->client->request('GET', '/positions/01a1a675-0000-7000-8000-000000000000');

        self::assertResponseStatusCodeSame(404);
    }

    /** @param array<string, string> $overrides */
    private function apply(array $overrides = []): \Symfony\Component\DomCrawler\Crawler
    {
        $crawler = $this->client->request('GET', '/positions/'.self::OPEN_POSITION);
        $values = $overrides + [
            'name' => 'Jane',
            'surname' => 'Doe',
            'email' => 'jane.doe@gmail.com',
            'phone' => '+34 600 000 000',
            'cv' => 'Ten years building backend systems.',
            'notes' => '',
        ];

        $fields = [];
        foreach ($values as $name => $value) {
            $fields["apply[$name]"] = $value;
        }

        return $this->client->submit($crawler->selectButton('Send application')->form(), $fields);
    }
}
