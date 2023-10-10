<?php

namespace App\Query;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\TotalUploadsQueryResult;
use App\Query\Trait\ScopeAwareTrait;
use App\Query\Trait\UploadTableAwareTrait;
use DateTime;
use DateTimeInterface;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Packages\Models\Model\Event\EventInterface;

class TotalUploadsQuery implements QueryInterface
{
    use UploadTableAwareTrait;
    use ScopeAwareTrait;

    public function getQuery(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        $commitScope = self::getCommitScope($parameterBag);
        $repositoryScope = self::getRepositoryScope($parameterBag);

        return <<<SQL
        SELECT
            COALESCE(ANY_VALUE(commit), '') as commit,
            ARRAY_AGG(uploadId) as successfulUploads,
            ARRAY_AGG(tag) as successfulTags,
            MAX(ingestTime) as latestSuccessfulUpload
        FROM
            `$table`
        WHERE
            {$commitScope} AND
            {$repositoryScope}
        GROUP BY
            tag
        SQL;
    }

    public function getNamedQueries(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        return '';
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
