<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Bus\Fixture;

use App\Shared\Domain\Event\DomainEvent;

final readonly class TestEvent implements DomainEvent
{
}
