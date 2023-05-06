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
        #[TaggedIterator('app.coverage_query')]
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
        foreach ($this->queries as $query) {
            if ($query::class === $queryClass) {
                return $this->runQueryAndParseResult($query, $upload);
            }
        }

        throw new QueryException(
            sprintf("No query found with class name of %s.", $queryClass)
        );
    }

    private function runQueryAndParseResult(QueryInterface $query, Upload $upload): mixed
    {
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
