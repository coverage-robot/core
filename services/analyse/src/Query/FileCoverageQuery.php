<?php

namespace App\Query;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\FileCoverageCollectionQueryResult;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Override;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Line\LineState;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class FileCoverageQuery extends AbstractLineCoverageQuery
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
        $covered = LineState::COVERED->value;
        $partial = LineState::PARTIAL->value;
        $uncovered = LineState::UNCOVERED->value;

        return <<<SQL
        {$this->getNamedQueries($table, $parameterBag)}
        SELECT
            fileName,
            COUNT(*) as lines,
            COALESCE(SUM(IF(state = "{$covered}", 1, 0)), 0) as covered,
            COALESCE(SUM(IF(state = "{$partial}", 1, 0)), 0) as partial,
            COALESCE(SUM(IF(state = "{$uncovered}", 1, 0)), 0) as uncovered,
            ROUND(
                (
                    SUM(IF(state = "{$covered}", 1, 0)) + SUM(IF(state = "{$partial}", 1, 0))
                ) /
                COUNT(*) * 100,
                2
            ) as coveragePercentage
        FROM
            lines
        GROUP BY
            fileName
        ORDER BY
            ROUND(
                (
                    SUM(IF(state = "{$covered}", 1, 0)) + SUM(IF(state = "{$partial}", 1, 0))
                ) /
                COUNT(*) * 100,
                2
            )
            ASC
        LIMIT {$this->getAlias(QueryParameter::LIMIT)}
        SQL;
    }

    #[Override]
    public function validateParameters(?QueryParameterBag $parameterBag = null): void
    {
        parent::validateParameters($parameterBag);

        if (!$parameterBag?->has(QueryParameter::LIMIT)) {
            throw QueryException::invalidParameters(QueryParameter::LIMIT);
        }
    }

    /**
     * @throws GoogleException
     * @throws QueryException
     * @throws ExceptionInterface
     */
    #[Override]
    public function parseResults(QueryResults $results): FileCoverageCollectionQueryResult
    {
        $files = $results->rows();

        /** @var FileCoverageCollectionQueryResult $results */
        $results = $this->serializer->denormalize(
            ['files' => iterator_to_array($files)],
            FileCoverageCollectionQueryResult::class,
            'array'
        );

        return $results;
    }
}
