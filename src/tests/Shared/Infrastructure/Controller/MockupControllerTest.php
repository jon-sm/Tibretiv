<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Controller;

use App\Shared\Infrastructure\Controller\MockupController;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MockupControllerTest extends WebTestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function pageStates(): iterable
    {
        foreach (MockupController::PAGES as $page => $states) {
            foreach ($states as $state) {
                yield "$page:$state" => [$page, $state];
            }
        }
    }

    #[DataProvider('pageStates')]
    public function testEveryPageRendersInEveryState(string $page, string $state): void
    {
        $client = static::createClient();
        $client->request('GET', "/mockups/$page?state=$state");

        self::assertResponseIsSuccessful();
    }

    public function testAnUnknownStateFallsBackToTheDefault(): void
    {
        $client = static::createClient();
        $client->request('GET', '/mockups/application?state=nope');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('section[aria-labelledby="ai"]', 'Summary');
    }

    public function testStatesChangeWhatIsShown(): void
    {
        $client = static::createClient();

        $client->request('GET', '/mockups/apply?state=closed');
        self::assertSelectorTextContains('#closed', 'Applications closed');
        self::assertSelectorNotExists('form');

        $client->request('GET', '/mockups/apply?state=errors');
        self::assertSelectorExists('#email[aria-invalid="true"]');

        $client->request('GET', '/mockups/applications?state=empty');
        self::assertSelectorTextContains('main', 'No applications yet.');
        self::assertSelectorNotExists('table');
    }

    public function testAnUnknownPageIsNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/mockups/nope');

        self::assertResponseStatusCodeSame(404);
    }
}
