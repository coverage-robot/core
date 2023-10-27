<?php

namespace App\Query;

use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\Trait\CarryforwardAwareTrait;
use App\Service\EnvironmentService;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Packages\Models\Enum\LineState;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class TotalTagCoverageQuery extends AbstractUnnestedLineMetadataQuery
{
    use CarryforwardAwareTrait;

    private const UPLOAD_TABLE_ALIAS = 'upload';

    /**
     * @param SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer
     */
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly EnvironmentService $environmentService
    ) {
        parent::__construct($environmentService);
    }

    public function getQuery(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        $covered = LineState::COVERED->value;
        $partial = LineState::PARTIAL->value;
        $uncovered = LineState::UNCOVERED->value;

        return <<<SQL
        {$this->getNamedQueries($table, $parameterBag)}
        SELECT
            STRUCT(tag as name, commit as commit),
            COUNT(*) as lines,
            COALESCE(SUM(IF(state = "{$covered}", 1, 0)), 0) as covered,
            COALESCE(SUM(IF(state = "{$partial}", 1, 0)), 0) as partial,
            COALESCE(SUM(IF(state = "{$uncovered}", 1, 0)), 0) as uncovered,
            ROUND(
                (
                    SUM(IF(state = "{$covered}", 1, 0)) +
                    SUM(IF(state = "{$partial}", 1, 0))
                ) /
                COUNT(*)
                * 100,
                2
            ) as coveragePercentage
        FROM
            lines
        GROUP BY
            tag,
            commit
        SQL;
    }

    public function getNamedQueries(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        $parent = parent::getNamedQueries($table, $parameterBag);

        $covered = LineState::COVERED->value;
        $partial = LineState::PARTIAL->value;
        $uncovered = LineState::UNCOVERED->value;

        return <<<SQL
        {$parent},
        branchingLines AS (
            SELECT
                fileName,
                lineNumber,
                tag,
                commit,
                SUM(hits) as hits,
                branchIndex,
                SUM(branchHit) > 0 as isBranchedLineHit
            FROM
                unnested,
                UNNEST(
                    IF(
                        ARRAY_LENGTH(branchHits) = 0,
                        [hits],
                        branchHits
                    )
                ) AS branchHit WITH OFFSET AS branchIndex
            GROUP BY
                fileName,
                lineNumber,
                tag,
                commit,
                branchIndex
        ),
        lines AS (
            SELECT
                tag,
                commit,
                fileName,
                lineNumber,
                IF(
                    SUM(hits) = 0,
                    "{$uncovered}",
                    IF (
                        MIN(CAST(isBranchedLineHit AS INT64)) = 0,
                        "{$partial}",
                        "{$covered}"
                    )
                ) as state
            FROM
                branchingLines
            GROUP BY
                tag,
                commit,
                fileName,
                lineNumber
        )
        SQL;
    }

    public function getUnnestQueryFiltering(string $table, ?QueryParameterBag $parameterBag): string
    {
        $parent = parent::getUnnestQueryFiltering($table, $parameterBag);
        $carryforwardScope = !empty(
            $scope = self::getCarryforwardTagsScope(
                $parameterBag,
                self::UPLOAD_TABLE_ALIAS
            )
        ) ? 'OR ' . $scope : '';

        return <<<SQL
        (
            {$parent}
        )
        {$carryforwardScope}
        SQL;
    }

    /**
     * @throws GoogleException
     * @throws QueryException
     * @throws ExceptionInterface
     */
    public function parseResults(QueryResults $results): TagCoverageCollectionQueryResult
    {
        if (!$results->isComplete()) {
            throw new QueryException('Query was not complete when attempting to parse results.');
        }

        $tags = $results->rows();

        return $this->serializer->denormalize(
            ['tags' => $tags],
            TagCoverageCollectionQueryResult::class,
            'array'
        );
    }
}
