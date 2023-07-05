<?php

namespace App\Query;

use App\Model\QueryParameterBag;
use Packages\Models\Model\Upload;

abstract class AbstractUnnestedLineMetadataQuery implements QueryInterface
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
                ARRAY(
                    SELECT
                        SUM(CAST(branchHits AS INT64))
                    FROM
                        UNNEST(
                            JSON_VALUE_ARRAY(
                                (
                                    SELECT
                                        value
                                    FROM
                                        UNNEST(metadata)
                                    WHERE
                                        KEY = "branchHits"
                                )
                            ) 
                        ) AS branchHits WITH OFFSET AS branchIndex
                    GROUP BY
                        branchIndex,
                        branchHits
                ) as branchHits
            FROM
                `$table`
            WHERE
                commit = '{$upload->getCommit()}' AND
                owner = '{$upload->getOwner()}' AND
                repository = '{$upload->getRepository()}'
                {$this->getUnnestQueryFiltering($parameterBag)}
        )
        SQL;
    }
}
