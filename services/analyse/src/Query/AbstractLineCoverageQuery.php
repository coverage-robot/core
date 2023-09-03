<?php

namespace App\Query;

use App\Model\QueryParameterBag;
use App\Query\Trait\ScopeAwareTrait;
use Packages\Models\Enum\LineState;

abstract class AbstractLineCoverageQuery extends AbstractUnnestedLineMetadataQuery
{
    use ScopeAwareTrait;

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
                branchIndex
        ),
        lines AS (
            SELECT
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
                fileName,
                lineNumber
        )
        SQL;
    }

    public function getUnnestQueryFiltering(string $table, ?QueryParameterBag $parameterBag): string
    {
        $parent = parent::getUnnestQueryFiltering($table, $parameterBag);
        $successfulUploadsScope = self::getSuccessfulUploadsScope(
            $table,
            $parameterBag
        );

        return <<<SQL
        {$parent}
        AND {$successfulUploadsScope}
        SQL;
    }
}
