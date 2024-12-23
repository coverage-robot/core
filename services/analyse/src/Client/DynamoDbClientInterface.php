<?php

declare(strict_types=1);

namespace App\Client;

use App\Query\Result\QueryResultInterface;

interface DynamoDbClientInterface
{
    public function tryFromQueryCache(string $cacheKey): ?QueryResultInterface;

    public function putQueryResultInCache(string $cacheKey, QueryResultInterface $queryResult): bool;
}
