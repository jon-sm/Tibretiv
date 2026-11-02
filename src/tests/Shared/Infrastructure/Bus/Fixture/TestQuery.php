<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Bus\Fixture;

use App\Shared\Application\Bus\Query\Query;

final readonly class TestQuery implements Query
{
    public function __construct(public string $value, public ?\Throwable $failWith = null)
    {
    }
}
