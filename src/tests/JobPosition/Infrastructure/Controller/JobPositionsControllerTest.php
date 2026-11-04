<?php

declare(strict_types=1);

namespace App\Tests\JobPosition\Infrastructure\Controller;

use App\Tests\Support\DatabaseTestCase;

final class JobPositionsControllerTest extends DatabaseTestCase
{
    public function testTheIndexListsOpenPositionsNewestFirst(): void
    {
        $client = self::createClient();
        $this->resetDatabase();
        $this->seedPositions();

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame(
            [self::OPEN_POSITION, self::OTHER_POSITION],
            $crawler->filter('[data-position]')->each(static fn ($node): string => (string) $node->attr('data-position')),
            'the closed position is not listed',
        );
    }

    public function testTheIndexHasAnEmptyState(): void
    {
        $client = self::createClient();
        $this->resetDatabase();

        $client->request('GET', '/');

        self::assertSelectorTextContains('main', 'No open positions right now.');
    }
}
