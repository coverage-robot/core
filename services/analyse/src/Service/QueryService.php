<?php

namespace App\Service;

use App\Client\BigQueryClient;
use App\Exception\QueryException;
use App\Model\PublishableCoverageDataInterface;
use App\Model\Upload;
use App\Query\QueryInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class QueryService
{
    public function __construct(
        private readonly BigQueryClient $bigQueryClient,
        #[TaggedIterator('app.coverage_queries')]
        private readonly iterable $queries
    ) {
    }

    /**
     * @param class-string $queryClass
     * @param Upload $upload
     * @return PublishableCoverageDataInterface|void
     *
     * @throws QueryException
     */
    public function runQuery(string $queryClass, Upload $upload): mixed
    {
        $query = array_filter(
            (array)$this->queries,
            static fn(QueryInterface $queryInstance) => $queryInstance::class === $queryClass
        );

        if (!$query) {
            throw new QueryException(
                sprintf("No query found with class name of %s.", $queryClass)
            );
        }

        /** @var QueryInterface $query */
        $job = $this->bigQueryClient->query(
            $query->getQuery(
                $this->bigQueryClient->getTable(),
                $upload
            )
        );

        $results = $this->bigQueryClient->runQuery($job);

        $results->waitUntilComplete();

        return $query->parseResults($results);
    }
}
