<?php

declare(strict_types=1);

namespace App\Query;

use App\Enum\EnvironmentVariable;
use App\Enum\QueryParameter;
use App\Model\CarryforwardTag;
use App\Model\QueryParameterBag;
use App\Query\Trait\BigQueryTableAwareTrait;
use App\Query\Trait\CarryforwardAwareTrait;
use App\Query\Trait\DiffAwareTrait;
use App\Query\Trait\ParameterAwareTrait;
use DateTimeInterface;
use Google\Cloud\BigQuery\BigQueryClient;
use Override;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Line\LineType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\AtLeastOneOf;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Uuid;

abstract class AbstractUnnestedLineMetadataQuery implements QueryInterface
{
    use CarryforwardAwareTrait;
    use DiffAwareTrait;
    use ParameterAwareTrait;
    use BigQueryTableAwareTrait;

    protected const string UPLOAD_TABLE_ALIAS = 'upload';

    protected const string LINES_TABLE_ALIAS = 'lines';

    public function __construct(
        private readonly EnvironmentServiceInterface $environmentService,
        private readonly BigQueryClient $bigQueryClient
    ) {
    }

    #[Override]
    abstract public function getQuery(?QueryParameterBag $parameterBag = null): string;

    #[Override]
    public function getNamedQueries(?QueryParameterBag $parameterBag = null): string
    {
        $uploadTableAlias = self::UPLOAD_TABLE_ALIAS;
        $linesTableAlias = self::LINES_TABLE_ALIAS;

        $uploadTable = $this->getTable(
            $this->environmentService->getVariable(EnvironmentVariable::BIGQUERY_UPLOAD_TABLE)
        );
        $lineCoverageTable = $this->getTable(
            $this->environmentService->getVariable(EnvironmentVariable::BIGQUERY_LINE_COVERAGE_TABLE)
        );

        $methodType = LineType::METHOD->value;
        $branchType = LineType::BRANCH->value;
        $statementType = LineType::STATEMENT->value;

        return <<<SQL
        WITH unnested AS (
            SELECT
                {$uploadTableAlias}.tag,
                {$uploadTableAlias}.totalLines,
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
                `{$uploadTable}` as {$uploadTableAlias}
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

        if (
            $parameterBag?->get(QueryParameter::UPLOADS) === null ||
            $parameterBag?->get(QueryParameter::UPLOADS) === []
        ) {
            return <<<SQL
            {$this->getCarryforwardTagsScope($parameterBag, self::UPLOAD_TABLE_ALIAS)}
            AND DATE({$linesTableAlias}.ingestTime) IN UNNEST({$this->getAlias(QueryParameter::INGEST_PARTITIONS)})
            {$lineScope}
            SQL;
        }

        if (
            $parameterBag?->get(QueryParameter::CARRYFORWARD_TAGS) === null ||
            $parameterBag?->get(QueryParameter::CARRYFORWARD_TAGS) === []
        ) {
            return <<<SQL
            1=1
            AND {$uploadsTableAlias}.projectId = {$this->getAlias(QueryParameter::PROJECT_ID)}
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
                AND {$uploadsTableAlias}.projectId = {$this->getAlias(QueryParameter::PROJECT_ID)}
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
     * @return array<value-of<QueryParameter>, Type[]|Uuid[]|AtLeastOneOf[]|Sequentially[]>
     */
    #[Override]
    public function getQueryParameterConstraints(): array
    {
        return [
            QueryParameter::PROJECT_ID->value => [
                new Assert\Type(type: 'string'),
                new Assert\Uuid(versions: [Assert\Uuid::V7_MONOTONIC])
            ],
            QueryParameter::COMMIT->value => [
                new Assert\AtLeastOneOf([
                    new Assert\Sequentially([
                        new Assert\Type(type: 'string'),
                        new Assert\Regex(pattern: '/^[a-f0-9]{40}$/')
                    ]),
                    new Assert\Sequentially([
                        new Assert\Type(type: 'array'),
                        new Assert\Count(min: 1),
                        new Assert\All([
                            new Assert\Type(type: 'string'),
                            new Assert\Regex(pattern: '/^[a-f0-9]{40}$/')
                        ])
                    ])
                ])
            ],

            /**
             * TODO(RM): Theres a link between the requirements of passing _at least_ ingest
             *  partitions and uploads, or carryforward tags. This should be enforced in the
             *  constraints.
             */
            QueryParameter::UPLOADS->value => [
                new Assert\Sequentially([
                    new Assert\Type(type: 'array'),
                    new Assert\All([
                        new Assert\Type(type: 'string'),
                        new Assert\Uuid(versions: [Assert\Uuid::V7_MONOTONIC])
                    ])
                ])
            ],
            QueryParameter::INGEST_PARTITIONS->value => [
                new Assert\Sequentially([
                    new Assert\Type(type: 'array'),
                    new Assert\All([
                        new Assert\Type(type: DateTimeInterface::class)
                    ])
                ])
            ],
            QueryParameter::CARRYFORWARD_TAGS->value => [
                new Assert\Sequentially([
                    new Assert\Type(type: 'array'),
                    new Assert\All([
                        new Assert\Type(type: CarryforwardTag::class)
                    ])
                ])
            ],
        ];
    }
}
