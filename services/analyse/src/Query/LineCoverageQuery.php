<?php

namespace App\Query;

use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\LineCoverageCollectionQueryResult;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Override;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class LineCoverageQuery extends AbstractLineCoverageQuery
{
    public function __construct(
        private readonly SerializerInterface&DenormalizerInterface $serializer,
        EnvironmentServiceInterface $environmentService
    ) {
        parent::__construct($environmentService);
    }

    #[Override]
    public function getQuery(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        return <<<SQL
        {$this->getNamedQueries($table, $parameterBag)}
        SELECT
            *
        FROM
            lines
        ORDER BY
            fileName ASC,
            lineNumber ASC
        SQL;
    }

    /**
     * @throws GoogleException
     * @throws QueryException
     * @throws ExceptionInterface
     */
    #[Override]
    public function parseResults(QueryResults $results): LineCoverageCollectionQueryResult
    {
        $lines = $results->rows();

        /** @var LineCoverageCollectionQueryResult $results */
        $results = $this->serializer->denormalize(
            ['lines' => iterator_to_array($lines)],
            LineCoverageCollectionQueryResult::class,
            'array'
        );

        return $results;
    }
}
