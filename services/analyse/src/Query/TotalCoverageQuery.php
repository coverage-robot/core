<?php

declare(strict_types=1);

namespace App\Query;

use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\TotalCoverageQueryResult;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Override;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Line\LineState;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class TotalCoverageQuery extends AbstractLineCoverageQuery
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
            SUM(lines) as `lines`,
            SUM(covered) as covered,
            SUM(partial) as `partial`,
            SUM(uncovered) as uncovered,
            (SUM(covered) + SUM(partial)) / IF(SUM(lines) = 0, 1, SUM(lines)) * 100 as coveragePercentage
        FROM
            summedCoverage
        SQL;
    }

    #[Override]
    public function getNamedQueries(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        $parent = parent::getNamedQueries($table, $parameterBag);

        $covered = LineState::COVERED->value;
        $partial = LineState::PARTIAL->value;
        $uncovered = LineState::UNCOVERED->value;

        return <<<SQL
        {$parent},
        summedCoverage AS (
            SELECT
                COUNT(*) as lines,
                COALESCE(SUM(IF(state = "{$covered}", 1, 0)), 0) as covered,
                COALESCE(SUM(IF(state = "{$partial}", 1, 0)), 0) as partial,
                COALESCE(SUM(IF(state = "{$uncovered}", 1, 0)), 0) as uncovered,
            FROM
                lines
        )
        SQL;
    }

    /**
     * @throws GoogleException
     * @throws QueryException
     * @throws ExceptionInterface
     */
    #[Override]
    public function parseResults(QueryResults $results): TotalCoverageQueryResult
    {
        /** @var array $coverageValues */
        $coverageValues = $results->rows()
            ->current();

        /** @var TotalCoverageQueryResult $results */
        $results = $this->serializer->denormalize(
            $coverageValues,
            TotalCoverageQueryResult::class,
            'array'
        );

        return $results;
    }
}
