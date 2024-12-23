<?php

declare(strict_types=1);

namespace App\Query;

use App\Client\BigQueryClient;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\QueryResultIterator;
use App\Query\Result\TagCoverageQueryResult;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Override;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Line\LineState;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class TotalTagCoverageQuery extends AbstractUnnestedLineMetadataQuery
{
    public function __construct(
        private readonly SerializerInterface&DenormalizerInterface $serializer,
        private readonly ValidatorInterface $validator,
        EnvironmentServiceInterface $environmentService,
        BigQueryClient $bigQueryClient
    ) {
        parent::__construct($environmentService, $bigQueryClient);
    }

    #[Override]
    public function getQuery(?QueryParameterBag $parameterBag = null): string
    {
        $covered = LineState::COVERED->value;
        $partial = LineState::PARTIAL->value;
        $uncovered = LineState::UNCOVERED->value;

        return <<<SQL
        {$this->getNamedQueries($parameterBag)}
        SELECT
            tag as tagName,
            STRUCT(tag as name, commit as `commit`, [totalLines] as successfullyUploadedLines) as tag,
            COUNT(*) as `lines`,
            COALESCE(SUM(IF(state = "{$covered}", 1, 0)), 0) as covered,
            COALESCE(SUM(IF(state = "{$partial}", 1, 0)), 0) as `partial`,
            COALESCE(SUM(IF(state = "{$uncovered}", 1, 0)), 0) as uncovered,
            ROUND(
                (
                    SUM(IF(state = "{$covered}", 1, 0)) +
                    SUM(IF(state = "{$partial}", 1, 0))
                ) /
                COUNT(*)
                * 100,
                2
            ) as coveragePercentage
        FROM
            lines
        GROUP BY
            tagName,
            totalLines,
            commit
        ORDER BY
            tagName ASC
        SQL;
    }

    #[Override]
    public function getNamedQueries(?QueryParameterBag $parameterBag = null): string
    {
        $parent = parent::getNamedQueries($parameterBag);

        $covered = LineState::COVERED->value;
        $partial = LineState::PARTIAL->value;
        $uncovered = LineState::UNCOVERED->value;

        return <<<SQL
        {$parent},
        branchingLines AS (
            SELECT
                fileName,
                lineNumber,
                tag,
                totalLines,
                commit,
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
                tag,
                totalLines,
                commit,
                branchIndex
        ),
        lines AS (
            SELECT
                tag,
                totalLines,
                commit,
                fileName,
                lineNumber,
                IF(
                    -- Check that the line hits are 0 (i.e. not executed) and that, if theres a branch, it's\n
                    -- definitely not been covered at all (as we'll want to show that as a partial line)\n
                    SUM(hits) = 0
                    AND COUNTIF(
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
                tag,
                commit,
                totalLines,
                fileName,
                lineNumber
        )
        SQL;
    }

    /**
     * @throws GoogleException
     * @throws QueryException
     * @throws ExceptionInterface
     */
    #[Override]
    public function parseResults(QueryResults $results): QueryResultIterator
    {
        $totalRows = $results->info()['totalRows'];
        if (!is_numeric($totalRows)) {
            throw new QueryException(
                sprintf(
                    'Invalid total rows count when parsing results as iterator for %s: %s',
                    self::class,
                    (string)$totalRows
                )
            );
        }

        return new QueryResultIterator(
            $results->rows(['maxResults' => 200]),
            (int)$totalRows,
            function (array $row) {
                $row = $this->serializer->denormalize(
                    $row,
                    TagCoverageQueryResult::class,
                    'json'
                );

                $errors = $this->validator->validate($row);

                if (count($errors) > 0) {
                    throw QueryException::invalidResult($row, $errors);
                }

                return $row;
            }
        );
    }
}
