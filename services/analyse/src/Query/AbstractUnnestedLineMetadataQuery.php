<?php

namespace App\Query;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Trait\ScopeAwareTrait;
use Packages\Models\Model\Upload;

abstract class AbstractUnnestedLineMetadataQuery implements QueryInterface
{
    use ScopeAwareTrait;

    public function getUnnestQueryFiltering(?QueryParameterBag $parameterBag): string
    {
        $commitScope = !empty($scope = self::getCommitScope($parameterBag)) ? $scope : '';
        $repositoryScope = !empty($scope = self::getRepositoryScope($parameterBag)) ? 'AND ' . $scope : '';

        // To avoid any race conditions, the only uploads which should be included are those _before_ the current
        // upload we're analysing. This is because we cannot guarantee the completeness of any coverage data which
        // is after what we're currently uploading.
        $ingestTimeScope = sprintf(
            'AND ingestTime <= "%s"',
            $parameterBag->get(QueryParameter::UPLOAD)
                ->getIngestTime()
                ->format('Y-m-d H:i:s')
        );

        return <<<SQL
        {$commitScope}
        {$ingestTimeScope}
        {$repositoryScope}
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
