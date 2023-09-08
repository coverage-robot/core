<?php

namespace App\Query;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\TotalUploadsQueryResult;
use App\Query\Trait\ScopeAwareTrait;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Packages\Models\Model\Upload;

class TotalUploadsQuery implements QueryInterface
{
    use ScopeAwareTrait;

    public function getQuery(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        $parent = self::getNamedQueries($table, $parameterBag);
        return <<<SQL
        {$parent}
        SELECT
            ARRAY_AGG(IF(successful = 1, uploadId, NULL) IGNORE NULLS) as successfulUploads,
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

        if (!is_array($row['successfulUploads'])) {
            throw QueryException::typeMismatch(gettype($row['successfulUploads']), 'array');
        }

        if (!is_array($row['pendingUploads'])) {
            throw QueryException::typeMismatch(gettype($row['pendingUploads']), 'array');
        }

        if ($row['latestSuccessfulUpload'] && !is_string($row['latestSuccessfulUpload'])) {
            throw QueryException::typeMismatch(gettype($row['latestSuccessfulUpload']), 'string');
        }

        return TotalUploadsQueryResult::from(
            $row['successfulUploads'],
            $row['pendingUploads'],
            $row['latestSuccessfulUpload']
        );
    }

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
