<?php

declare(strict_types=1);

namespace App\Query;

use App\Client\BigQueryClient;
use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\FileCoverageQueryResult;
use App\Query\Result\QueryResultIterator;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Override;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Line\LineState;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class FileCoverageQuery extends AbstractLineCoverageQuery
{
    public function __construct(
        private readonly SerializerInterface&DenormalizerInterface $serializer,
        private readonly ValidatorInterface $validator,
        EnvironmentServiceInterface $environmentService,
        BigQueryClient $bigQueryClient
    ) {
        parent::__construct($environmentService, $bigQueryClient);
    }

    #[Override]
    public function getQuery(?QueryParameterBag $parameterBag = null): string
    {
        $covered = LineState::COVERED->value;
        $partial = LineState::PARTIAL->value;
        $uncovered = LineState::UNCOVERED->value;

        $limit = $parameterBag?->has(QueryParameter::LIMIT) === true ?
            sprintf('LIMIT %d', $this->getAlias(QueryParameter::LIMIT)) :
            null;

        return <<<SQL
        {$this->getNamedQueries($parameterBag)}
        SELECT
            fileName,
            ARRAY_AGG(lineNumber) as `lines`,
            ARRAY_AGG(IF(state = "{$covered}", lineNumber, null) IGNORE NULLS ORDER BY lineNumber ASC) AS coveredLines,
            ARRAY_AGG(IF(state = "{$partial}", lineNumber, null) IGNORE NULLS ORDER BY lineNumber ASC) AS partialLines,
            ARRAY_AGG(
                IF(state = "{$uncovered}", lineNumber, null) IGNORE NULLS ORDER BY lineNumber ASC
            ) AS uncoveredLines,
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
        {$limit}
        SQL;
    }

    /**
     * @throws GoogleException
     * @throws QueryException
     */
    #[Override]
    public function parseResults(QueryResults $results): QueryResultIterator
    {
        $totalRows = $results->info()['totalRows'];
        if (!is_numeric($totalRows)) {
            throw new QueryException(
                sprintf(
                    'Invalid total rows count when parsing results as iterator for %s: %s',
                    self::class,
                    (string)$totalRows
                )
            );
        }

        return new QueryResultIterator(
            $results->rows(['maxResults' => 200]),
            (int)$totalRows,
            function (array $row) {
                $row = $this->serializer->denormalize(
                    $row,
                    FileCoverageQueryResult::class,
                    'json'
                );

                $errors = $this->validator->validate($row);

                if (count($errors) > 0) {
                    throw QueryException::invalidResult($row, $errors);
                }

                return $row;
            }
        );
    }
}
