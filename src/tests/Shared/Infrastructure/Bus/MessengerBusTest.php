<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Event\EventBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Tests\Shared\Infrastructure\Bus\Fixture\TestCommand;
use App\Tests\Shared\Infrastructure\Bus\Fixture\TestEvent;
use App\Tests\Shared\Infrastructure\Bus\Fixture\TestQuery;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class MessengerBusTest extends KernelTestCase
{
    public function testCommandRunsSynchronously(): void
    {
        $command = new TestCommand();

        self::getContainer()->get(CommandBus::class)->dispatch($command);

        self::assertTrue($command->handled);
    }

    public function testCommandHandlerExceptionReachesTheCallerUnwrapped(): void
    {
        $this->expectExceptionObject(new \DomainException('rejected'));

        self::getContainer()->get(CommandBus::class)->dispatch(new TestCommand(new \DomainException('rejected')));
    }

    public function testQueryReturnsTheHandlerResult(): void
    {
        self::assertSame('result', self::getContainer()->get(QueryBus::class)->ask(new TestQuery('result')));
    }

    public function testQueryHandlerExceptionReachesTheCallerUnwrapped(): void
    {
        $this->expectExceptionObject(new \DomainException('not found'));

        self::getContainer()->get(QueryBus::class)->ask(new TestQuery('result', new \DomainException('not found')));
    }

    public function testEventsAreSentToTheAsyncTransport(): void
    {
        self::getContainer()->get(EventBus::class)->publish(new TestEvent(), new TestEvent());

        $transport = self::getContainer()->get('messenger.transport.async');

        self::assertInstanceOf(InMemoryTransport::class, $transport);
        self::assertCount(2, $transport->getSent());
    }
}
