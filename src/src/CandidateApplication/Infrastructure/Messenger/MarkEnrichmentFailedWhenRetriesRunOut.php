<?php

declare(strict_types=1);

namespace App\CandidateApplication\Infrastructure\Messenger;

use App\CandidateApplication\Application\MarkEnrichmentFailed\MarkEnrichmentFailed;
use App\CandidateApplication\Domain\Event\CandidateApplicationSubmitted;
use App\Shared\Application\Bus\Command\CommandBus;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * Messenger retries a failed enrichment (see the async transport's retry strategy). After
 * the last attempt, the message goes to the failure transport and the application is marked
 * as failed so the UI stops waiting.
 */
#[AsEventListener]
final readonly class MarkEnrichmentFailedWhenRetriesRunOut
{
    public function __construct(private CommandBus $commands)
    {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();

        if ($event->willRetry() || !$message instanceof CandidateApplicationSubmitted) {
            return;
        }

        $this->commands->dispatch(new MarkEnrichmentFailed($message->applicationId));
    }
}
