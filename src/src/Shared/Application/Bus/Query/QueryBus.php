<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus\Query;

interface QueryBus
{
    /**
     * @return mixed the value returned by the query handler
     */
    public function ask(Query $query): mixed;
}
