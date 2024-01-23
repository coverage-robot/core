<?php

namespace App\Query;

use App\Enum\EnvironmentVariable;
use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Trait\CarryforwardAwareTrait;
use App\Query\Trait\DiffAwareTrait;
use App\Query\Trait\ParameterAwareTrait;
use App\Query\Trait\UploadTableAwareTrait;
use Override;
use Packages\Contracts\Line\LineType;

abstract class AbstractUnnestedLineMetadataQuery implements QueryInterface
{
    use CarryforwardAwareTrait;
    use UploadTableAwareTrait;
    use DiffAwareTrait;
    use ParameterAwareTrait;

    protected const UPLOAD_TABLE_ALIAS = 'upload';

    protected const LINES_TABLE_ALIAS = 'lines';

    #[Override]
    abstract public function getQuery(string $table, ?QueryParameterBag $parameterBag = null): string;

    #[Override]
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
                {$this->getUnnestQueryFiltering($parameterBag)}
        )
        SQL;
    }

    private function getUnnestQueryFiltering(?QueryParameterBag $parameterBag): string
    {
        $uploadsTableAlias = self::UPLOAD_TABLE_ALIAS;
        $linesTableAlias = self::LINES_TABLE_ALIAS;

        $lineScope = ($scope = $this->getLineScope($parameterBag, self::LINES_TABLE_ALIAS)) !== ''
            ? 'AND ' . $scope
            : '';

        if ($parameterBag?->get(QueryParameter::UPLOADS) === null) {
            return <<<SQL
            {$this->getCarryforwardTagsScope($parameterBag, self::UPLOAD_TABLE_ALIAS)}
            AND DATE({$linesTableAlias}.ingestTime) IN UNNEST({$this->getAlias(QueryParameter::INGEST_PARTITIONS)})
            {$lineScope}
            SQL;
        }

        if ($parameterBag?->get(QueryParameter::CARRYFORWARD_TAGS) === null) {
            return <<<SQL
            1=1
            AND {$uploadsTableAlias}.provider = {$this->getAlias(QueryParameter::PROVIDER)}
            AND {$uploadsTableAlias}.owner = {$this->getAlias(QueryParameter::OWNER)}
            AND {$uploadsTableAlias}.repository = {$this->getAlias(QueryParameter::REPOSITORY)}
            AND {$uploadsTableAlias}.commit = {$this->getAlias(QueryParameter::COMMIT)}
            AND {$uploadsTableAlias}.uploadId IN UNNEST({$this->getAlias(QueryParameter::UPLOADS)})
            AND DATE({$linesTableAlias}.ingestTime) IN UNNEST({$this->getAlias(QueryParameter::INGEST_PARTITIONS)})
            {$lineScope}
            SQL;
        }

        return <<<SQL
        (
            (
                1=1
                AND {$uploadsTableAlias}.provider = {$this->getAlias(QueryParameter::PROVIDER)}
                AND {$uploadsTableAlias}.owner = {$this->getAlias(QueryParameter::OWNER)}
                AND {$uploadsTableAlias}.repository = {$this->getAlias(QueryParameter::REPOSITORY)}
                AND {$uploadsTableAlias}.commit = {$this->getAlias(QueryParameter::COMMIT)}
                AND {$uploadsTableAlias}.uploadId IN UNNEST({$this->getAlias(QueryParameter::UPLOADS)})
            )
            OR {$this->getCarryforwardTagsScope($parameterBag, self::UPLOAD_TABLE_ALIAS)}
        )
        AND DATE({$linesTableAlias}.ingestTime) IN UNNEST({$this->getAlias(QueryParameter::INGEST_PARTITIONS)})
        {$lineScope}
        SQL;
    }

    /**
     * @throws QueryException
     */
    #[Override]
    public function validateParameters(?QueryParameterBag $parameterBag = null): void
    {
        if (!$parameterBag?->has(QueryParameter::COMMIT)) {
            throw QueryException::invalidParameters(QueryParameter::COMMIT);
        }

        if (!$parameterBag->has(QueryParameter::REPOSITORY)) {
            throw QueryException::invalidParameters(QueryParameter::REPOSITORY);
        }

        if (
            (
                !$parameterBag->get(QueryParameter::INGEST_PARTITIONS) ||
                !$parameterBag->get(QueryParameter::UPLOADS)
            ) &&
            !$parameterBag->get(QueryParameter::CARRYFORWARD_TAGS)
        ) {
            throw new QueryException(
                'You must provide either an ingest time scope and uploads scope, or carryforward tags.'
            );
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
    #[Override]
    public function isCachable(): bool
    {
        return true;
    }
}
