<?php

namespace App\Query;

use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\FileCoverageCollectionQueryResult;
use App\Query\Trait\CarryforwardAwareTrait;
use App\Query\Trait\DiffAwareTrait;
use App\Query\Trait\ScopeAwareTrait;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Packages\Models\Enum\LineState;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class FileCoverageQuery extends AbstractLineCoverageQuery
{
    use ScopeAwareTrait;
    use DiffAwareTrait;
    use CarryforwardAwareTrait;

    private const LINES_TABLE_ALIAS = 'lines';
    private const UPLOAD_TABLE_ALIAS = 'upload';

    /**
     * @param SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer
     */
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly EnvironmentServiceInterface $environmentService
    ) {
        parent::__construct($environmentService);
    }

    public function getQuery(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        $covered = LineState::COVERED->value;
        $partial = LineState::PARTIAL->value;
        $uncovered = LineState::UNCOVERED->value;

        $limit = self::getLimit($parameterBag);

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
        {$limit}
        SQL;
    }

    public function getUnnestQueryFiltering(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        $parent = parent::getUnnestQueryFiltering($table, $parameterBag);
        $carryforwardScope = !empty(
            $scope = self::getCarryforwardTagsScope(
                $parameterBag,
                self::UPLOAD_TABLE_ALIAS
            )
        ) ? 'OR ' . $scope : '';
        $lineScope = !empty(
            $scope = self::getLineScope(
                $parameterBag,
                self::LINES_TABLE_ALIAS
            )
        ) ? 'AND ' . $scope : '';

        return <<<SQL
        (
            (
                {$parent}
            )
            {$carryforwardScope}
        )
        {$lineScope}
        SQL;
    }

    /**
     * @throws GoogleException
     * @throws QueryException
     * @throws ExceptionInterface
     */
    public function parseResults(QueryResults $results): FileCoverageCollectionQueryResult
    {
        if (!$results->isComplete()) {
            throw new QueryException('Query was not complete when attempting to parse results.');
        }

        $files = $results->rows();

        return $this->serializer->denormalize(
            ['files' => iterator_to_array($files)],
            FileCoverageCollectionQueryResult::class,
            'array'
        );
    }
}
