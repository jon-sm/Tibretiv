<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Bus\Fixture;

use App\Shared\Application\Bus\Command\CommandHandler;

final class TestCommandHandler implements CommandHandler
{
    public function __invoke(TestCommand $command): void
    {
        if (null !== $command->failWith) {
            throw $command->failWith;
        }

        $command->handled = true;
    }
}
