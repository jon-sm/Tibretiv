<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Bus\Fixture;

use App\Shared\Application\Bus\Query\QueryHandler;

final class TestQueryHandler implements QueryHandler
{
    public function __invoke(TestQuery $query): string
    {
        if (null !== $query->failWith) {
            throw $query->failWith;
        }

        return $query->value;
    }
}
