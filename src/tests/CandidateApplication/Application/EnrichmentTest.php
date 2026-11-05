<?php

declare(strict_types=1);

namespace App\Tests\CandidateApplication\Application;

use App\CandidateApplication\Domain\CandidateApplication;
use App\CandidateApplication\Infrastructure\Ai\MockCvAnalyser;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application as Console;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Runs the real worker against the in-memory async transport.
 */
final class EnrichmentTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        $this->resetDatabase();
        $this->seedPositions();

        // The transport only redelivers a retry once its backoff has elapsed on the (frozen)
        // test clock: let the backoff pass after each failed attempt.
        $dispatcher = self::getContainer()->get('event_dispatcher');
        \assert($dispatcher instanceof EventDispatcherInterface);
        $dispatcher->addListener(WorkerMessageFailedEvent::class, fn () => $this->clock()->modify('+1 minute'), -1000);
    }

    public function testTheWorkerAddsSummaryAndScore(): void
    {
        $this->submit(cv: 'Ten years building backend systems in PHP.');
        $this->clock()->modify('+5 seconds');

        $this->consume(messages: 1);

        $enrichment = $this->enrichment();
        self::assertSame('completed', $enrichment['status']);
        self::assertSame('Ten years building backend systems in PHP.', $enrichment['summary']);
        self::assertGreaterThanOrEqual(MockCvAnalyser::SCORE_MIN, $enrichment['score']);
        self::assertLessThanOrEqual(MockCvAnalyser::SCORE_MAX, $enrichment['score']);
        self::assertEquals(new \DateTimeImmutable('2026-11-04 10:00:05 UTC'), $enrichment['enrichedAt']->toDateTime());
        self::assertCount(0, $this->failedTransport()->getSent());
    }

    public function testItIsMarkedFailedOnceTheRetriesRunOut(): void
    {
        $this->submit(cv: 'My CV. '.MockCvAnalyser::FAILURE_TRIGGER);

        // The first attempt and 3 retries.
        $this->consume(messages: 4);

        $enrichment = $this->enrichment();
        self::assertSame('failed', $enrichment['status']);
        self::assertNull($enrichment['score']);
        self::assertNull($enrichment['summary']);
        self::assertCount(1, $this->failedTransport()->getSent(), 'kept in the failure transport');
    }

    public function testItStaysPendingWhileRetriesRemain(): void
    {
        $this->submit(cv: 'My CV. '.MockCvAnalyser::FAILURE_TRIGGER);

        $this->consume(messages: 2);

        self::assertSame('pending', $this->enrichment()['status']);
    }

    public function testARedeliveredEventDoesNotEnrichTwice(): void
    {
        $this->submit();
        $event = $this->asyncTransport()->getSent()[0];
        $this->consume(messages: 1);
        $first = $this->enrichment();

        $this->clock()->modify('+1 hour');
        $this->asyncTransport()->send($event);
        $this->consume(messages: 1);

        self::assertEquals($first, $this->enrichment());
    }

    /** --no-reset (null, not true): resetting services between messages would empty the in-memory transport. */
    private function consume(int $messages): void
    {
        $console = new Console(self::$kernel ?? throw new \LogicException('Kernel not booted.'));
        $tester = new CommandTester($console->find('messenger:consume'));
        $tester->execute(['receivers' => ['async'], '--limit' => $messages, '--time-limit' => 5, '--no-reset' => null]);
        $tester->assertCommandIsSuccessful();
        $this->documentManager()->clear();
    }

    /** @return array<string, mixed> */
    private function enrichment(): array
    {
        $document = $this->collection(CandidateApplication::class)->findOne();
        \assert(null !== $document);

        return (array) ((array) $document)['enrichment'];
    }

    private function failedTransport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.failed');
        \assert($transport instanceof InMemoryTransport);

        return $transport;
    }
}
