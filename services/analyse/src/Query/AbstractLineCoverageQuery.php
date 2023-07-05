<?php

namespace App\Query;

use App\Model\QueryParameterBag;
use Packages\Models\Enum\LineState;
use Packages\Models\Model\Upload;

abstract class AbstractLineCoverageQuery extends AbstractUnnestedLineMetadataQuery
{
    public function getNamedQueries(string $table, Upload $upload, ?QueryParameterBag $parameterBag = null): string
    {
        $parent = parent::getNamedQueries($table, $upload, $parameterBag);

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
}
