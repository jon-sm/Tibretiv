<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Application\Bus\Query\QueryBus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class MessengerQueryBus implements QueryBus
{
    public function __construct(
        #[Autowire(service: 'query.bus')]
        private MessageBusInterface $bus,
    ) {
    }

    public function ask(Query $query): mixed
    {
        try {
            $envelope = $this->bus->dispatch($query);
        } catch (HandlerFailedException $exception) {
            // Callers should see the domain exception, not Messenger's wrapper.
            throw $exception->getPrevious() ?? $exception;
        }

        $stamp = $envelope->last(HandledStamp::class);

        if (null === $stamp) {
            throw new LogicException(\sprintf('No result was returned for query "%s".', $query::class));
        }

        return $stamp->getResult();
    }
}
