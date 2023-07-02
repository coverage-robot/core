<?php

namespace App\Query;

use App\Model\QueryParameterBag;
use Packages\Models\Model\Upload;

abstract class AbstractLineCoverageQuery implements QueryInterface
{
    public function getUnnestQueryFiltering(?QueryParameterBag $parameterBag): string
    {
        return '';
    }

    abstract public function getQuery(string $table, Upload $upload, ?QueryParameterBag $parameterBag = null): string;

    public function getNamedQueries(string $table, Upload $upload, ?QueryParameterBag $parameterBag = null): string
    {
        return <<<SQL
        WITH unnested AS (
            SELECT
                *,
                (
                    SELECT
                    IF (
                      value <> '',
                      CAST(value AS int),
                      0
                    )
                    FROM
                        UNNEST(metadata)
                    WHERE
                        key = "lineHits"
                ) AS hits,
                IF (
                    type = "BRANCH",
                    (
                        SELECT
                          IF (
                            value <> '',
                            CAST(value AS int),
                            0
                          )
                        FROM
                          UNNEST(metadata)
                        WHERE
                          KEY = "partial"
                    ),
                    0
                ) AS isPartiallyHit
            FROM
                `$table`
            WHERE
                commit = '{$upload->getCommit()}' AND
                owner = '{$upload->getOwner()}' AND
                repository = '{$upload->getRepository()}'
                {$this->getUnnestQueryFiltering($parameterBag)}
        ),
        lineCoverage AS (
            SELECT
                fileName,
                lineNumber,
                tag,
                SUM(hits) as hits,
                MIN(isPartiallyHit) as isPartiallyHit
            FROM
                unnested
            GROUP BY
                fileName,
                lineNumber,
                tag
        )
        SQL;
    }
}
