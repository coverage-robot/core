<?php

namespace App\Query;

use App\Model\QueryParameterBag;
use Override;
use Packages\Contracts\Line\LineState;

abstract class AbstractLineCoverageQuery extends AbstractUnnestedLineMetadataQuery
{
    #[Override]
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
                MAX(containsMethod) as containsMethod,
                MAX(containsBranch) as containsBranch,
                MAX(containsStatement) as containsStatement,
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
                MAX(containsMethod) as containsMethod,
                MAX(containsBranch) as containsBranch,
                MAX(containsStatement) as containsStatement,
                COUNTIF(containsBranch = true) as totalBranches,
                COUNTIF(
                    containsBranch = true AND
                    isBranchedLineHit = true
                ) as coveredBranches,
                IF(
                    -- Check that the line hits are 0 (i.e. not executed) and that, if theres a branch, it's
                    -- definitely not been covered at all (as we'll want to show that as a partial line)
                    SUM(hits) = 0 AND
                    COUNTIF(
                        containsBranch = true
                        AND isBranchedLineHit = true
                    ) = 0,
                    "{$uncovered}",
                    IF (
                        MIN(isBranchedLineHit) = false,
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
