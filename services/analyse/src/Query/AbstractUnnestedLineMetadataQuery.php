<?php

namespace App\Query;

use App\Enum\EnvironmentVariable;
use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Trait\ScopeAwareTrait;
use App\Query\Trait\UploadTableAwareTrait;
use Packages\Contracts\Line\LineType;

abstract class AbstractUnnestedLineMetadataQuery implements QueryInterface
{
    use ScopeAwareTrait;
    use UploadTableAwareTrait;

    protected const UPLOAD_TABLE_ALIAS = 'upload';

    protected const LINES_TABLE_ALIAS = 'lines';

    public function getUnnestQueryFiltering(string $table, ?QueryParameterBag $parameterBag): string
    {
        $commitScope = ($scope = self::getCommitScope($parameterBag, self::UPLOAD_TABLE_ALIAS)) === '' ? '' : $scope;
        $ingestTimeScope = ($scope = self::getIngestTimeScope(
            $parameterBag,
            self::LINES_TABLE_ALIAS
        )) === '' ? '' : 'AND ' . $scope;
        $repositoryScope = ($scope = self::getRepositoryScope(
            $parameterBag,
            self::UPLOAD_TABLE_ALIAS
        )) === '' ? '' : 'AND ' . $scope;
        $uploadScope = ($scope = self::getUploadsScope(
            $parameterBag,
            self::UPLOAD_TABLE_ALIAS
        )) === '' ? '' : 'AND ' . $scope;

        return <<<SQL
        {$commitScope}
        {$ingestTimeScope}
        {$repositoryScope}
        {$uploadScope}
        SQL;
    }

    abstract public function getQuery(string $table, ?QueryParameterBag $parameterBag = null): string;

    public function getNamedQueries(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        // TODO(RM): We should do this better. We need to get line coverage table in the same
        //  dataset as the upload table.
        $uploadTableAlias = self::UPLOAD_TABLE_ALIAS;
        $linesTableAlias = self::LINES_TABLE_ALIAS;
        $lineCoverageTable = implode(
            '.',
            [
                ...explode('.', $table, -1),
                $this->environmentService->getVariable(EnvironmentVariable::BIGQUERY_LINE_COVERAGE_TABLE)
            ]
        );

        $methodType = LineType::METHOD->value;
        $branchType = LineType::BRANCH->value;
        $statementType = LineType::STATEMENT->value;

        return <<<SQL
        WITH unnested AS (
            SELECT
                {$uploadTableAlias}.tag,
                {$uploadTableAlias}.commit,
                fileName,
                lineNumber,
                type = '{$methodType}' as containsMethod,
                type = '{$branchType}' as containsBranch,
                type = '{$statementType}' as containsStatement,
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
                `{$table}` as upload
            INNER JOIN `{$lineCoverageTable}` as {$linesTableAlias} ON lines.uploadId = upload.uploadId
            WHERE
                {$this->getUnnestQueryFiltering($table, $parameterBag)}
        )
        SQL;
    }

    /**
     * @throws QueryException
     */
    public function validateParameters(?QueryParameterBag $parameterBag = null): void
    {
        if (!$parameterBag?->has(QueryParameter::COMMIT)) {
            throw QueryException::invalidParameters(QueryParameter::COMMIT);
        }

        if (!$parameterBag->has(QueryParameter::REPOSITORY)) {
            throw QueryException::invalidParameters(QueryParameter::REPOSITORY);
        }
    }

    /**
     * Anything which inherits this abstract class is generally a coverage analysis query
     * (i.e. analysing line coverage using a set of tags/uploads), so therefore we should
     * be fine to cache these for extended periods of time.
     *
     * These are also **by far** the most expensive queries we run, simply by way of the
     * amount of data they're querying over (potentially multiple GBs).
     */
    public function isCachable(): bool
    {
        return true;
    }
}
