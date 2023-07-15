<?php

namespace App\Query;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use Packages\Models\Model\Upload;

abstract class AbstractUnnestedLineMetadataQuery implements QueryInterface
{
    public function getUnnestQueryFiltering(?QueryParameterBag $parameterBag): string
    {
        return <<<SQL
            commit = '{$parameterBag->get(QueryParameter::UPLOAD)->getCommit()}'
        SQL;
    }

    abstract public function getQuery(string $table, ?QueryParameterBag $parameterBag = null): string;

    public function getNamedQueries(string $table, ?QueryParameterBag $parameterBag = null): string
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
                owner = '{$parameterBag->get(QueryParameter::UPLOAD)->getOwner()}' AND
                repository = '{$parameterBag->get(QueryParameter::UPLOAD)->getRepository()}' AND
                {$this->getUnnestQueryFiltering($parameterBag)}
        )
        SQL;
    }

    /**
     * @throws QueryException
     */
    public function validateParameters(?QueryParameterBag $parameterBag = null): void
    {
        if (
            !$parameterBag ||
            !$parameterBag->has(QueryParameter::UPLOAD) ||
            !($parameterBag->get(QueryParameter::UPLOAD) instanceof Upload)
        ) {
            throw QueryException::invalidParameters(QueryParameter::UPLOAD);
        }
    }
}
