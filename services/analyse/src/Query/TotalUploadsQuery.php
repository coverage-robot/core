<?php

namespace App\Query;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\TotalUploadsQueryResult;
use App\Query\Trait\ScopeAwareTrait;
use DateTime;
use DateTimeInterface;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Packages\Models\Model\Event\EventInterface;

class TotalUploadsQuery implements QueryInterface
{
    use ScopeAwareTrait;

    public function getQuery(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        $parent = self::getNamedQueries($table, $parameterBag);
        return <<<SQL
        {$parent}
        SELECT
            COALESCE(ANY_VALUE(commit), '') as commit,
            ARRAY_AGG(IF(successful = 1, uploadId, NULL) IGNORE NULLS) as successfulUploads,
            ARRAY_AGG(IF(successful = 1, tag, NULL) IGNORE NULLS) as successfulTags,
            ARRAY_AGG(IF(pending = 1, uploadId, NULL) IGNORE NULLS) as pendingUploads,
            MAX(IF(successful = 1, ingestTime, NULL)) as latestSuccessfulUpload
        FROM
            uploads
        SQL;
    }

    public function getNamedQueries(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        $commitScope = self::getCommitScope($parameterBag);
        $repositoryScope = self::getRepositoryScope($parameterBag);

        return <<<SQL
        WITH uploads AS (
            SELECT
                uploadId,
                tag,
                commit,
                IF(COUNT(uploadId) >= totalLines, 1, 0) as successful,
                IF(COUNT(uploadId) < totalLines, 1, 0) as pending,
                ingestTime
            FROM
                `$table`
            WHERE
                {$commitScope} AND
                {$repositoryScope}
            GROUP BY
                uploadId,
                tag,
                commit,
                totalLines,
                ingestTime
        )
        SQL;
    }

    /**
     * @throws GoogleException
     * @throws QueryException
     */
    public function parseResults(QueryResults $results): TotalUploadsQueryResult
    {
        if (!$results->isComplete()) {
            throw new QueryException('Query was not complete when attempting to parse results.');
        }

        /** @var array $row */
        $row = $results->rows()
            ->current();

        if (!is_string($row['commit'])) {
            throw QueryException::typeMismatch(gettype($row['commit']), 'string');
        }

        if (!is_array($row['successfulUploads'])) {
            throw QueryException::typeMismatch(gettype($row['successfulUploads']), 'array');
        }

        if (!is_array($row['successfulTags'])) {
            throw QueryException::typeMismatch(gettype($row['successfulTags']), 'array');
        }

        if (!is_array($row['pendingUploads'])) {
            throw QueryException::typeMismatch(gettype($row['pendingUploads']), 'array');
        }

        if (
            !is_null($row['latestSuccessfulUpload']) &&
            !$row['latestSuccessfulUpload'] instanceof DateTime
        ) {
            throw QueryException::typeMismatch(gettype($row['latestSuccessfulUpload']), 'DateTime or null');
        }

        return TotalUploadsQueryResult::from(
            $row['commit'],
            $row['successfulUploads'],
            $row['successfulTags'],
            $row['pendingUploads'],
            $row['latestSuccessfulUpload']?->format(DateTimeInterface::ATOM)
        );
    }

    public function validateParameters(?QueryParameterBag $parameterBag = null): void
    {
        if (
            !$parameterBag ||
            !$parameterBag->has(QueryParameter::EVENT) ||
            !($parameterBag->get(QueryParameter::EVENT) instanceof EventInterface)
        ) {
            throw QueryException::invalidParameters(QueryParameter::EVENT);
        }
    }
}
