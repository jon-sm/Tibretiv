<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DesignTokensControllerTest extends WebTestCase
{
    public function testItRendersTheStyleGuide(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/design-tokens');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Design tokens');
        self::assertSame(
            ['Color: semantic', 'Color: palette in use', 'Typography', 'Spacing', 'Radius', 'Elevation', 'Components'],
            $crawler->filter('main section h2')->each(static fn ($h2): string => $h2->text()),
        );
        self::assertCount(1, $crawler->filter('main .bg-accent.h-16'));
    }

    public function testItRendersEveryComponent(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/design-tokens');

        $buttons = 'section[aria-labelledby="dt-components"] button';
        self::assertCount(1, $crawler->filter($buttons.'.h-8'));
        self::assertCount(2, $crawler->filter($buttons.'.h-10'), 'md, and the disabled default');
        self::assertCount(1, $crawler->filter($buttons.'.h-12'));
        self::assertCount(1, $crawler->filter($buttons.'[disabled]'));
        self::assertCount(1, $crawler->filter('input#dt-input[type="email"]'));
        self::assertCount(1, $crawler->filter('select#dt-select option[value="contacted"]'));
        self::assertCount(1, $crawler->filter('input#dt-date[type="date"]'));
        self::assertCount(1, $crawler->filter('textarea#dt-textarea[rows="6"]'));
        self::assertCount(1, $crawler->filter('label[for="dt-input"]'));
        self::assertSelectorTextContains('#dt-input-error ~ p', 'Enter a valid email address.');
    }

    public function testItLoadsTheAppEntrypoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/design-tokens');

        self::assertStringContainsString('"app"', (string) $client->getResponse()->getContent());
    }
}
