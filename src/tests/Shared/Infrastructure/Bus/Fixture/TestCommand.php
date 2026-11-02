<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Bus\Fixture;

use App\Shared\Application\Bus\Command\Command;

final class TestCommand implements Command
{
    public bool $handled = false;

    public function __construct(public readonly ?\Throwable $failWith = null)
    {
    }
}
