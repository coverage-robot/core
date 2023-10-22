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
use Packages\Models\Enum\Provider;

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
            "{$parameterBag?->get(QueryParameter::COMMIT)}" as commit,
            COALESCE(ARRAY_AGG(uploadId), []) as successfulUploads,
            COALESCE(ARRAY_AGG(tag), []) as successfulTags,
            COALESCE(MAX(ingestTime), NULL) as latestSuccessfulUpload
        FROM
            `$table`
        WHERE
            {$commitScope} AND
            {$repositoryScope}
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
        if (!$parameterBag) {
            throw new QueryException(
                sprintf('Query %s requires parameters to be provided.', self::class)
            );
        }

        if (
            !$parameterBag->has(QueryParameter::COMMIT) ||
            !(
                is_array($parameterBag->get(QueryParameter::COMMIT)) ||
                is_string($parameterBag->get(QueryParameter::COMMIT))
            ) ||
            empty($parameterBag->get(QueryParameter::COMMIT))
        ) {
            throw QueryException::invalidParameters(QueryParameter::COMMIT);
        }

        if (
            !$parameterBag->has(QueryParameter::REPOSITORY) ||
            !is_string($parameterBag->get(QueryParameter::REPOSITORY))
        ) {
            throw QueryException::invalidParameters(QueryParameter::REPOSITORY);
        }

        if (
            !$parameterBag->has(QueryParameter::OWNER) ||
            !is_string($parameterBag->get(QueryParameter::OWNER))
        ) {
            throw QueryException::invalidParameters(QueryParameter::OWNER);
        }

        if (
            !$parameterBag->has(QueryParameter::PROVIDER) ||
            !$parameterBag->get(QueryParameter::PROVIDER) instanceof Provider
        ) {
            throw QueryException::invalidParameters(QueryParameter::PROVIDER);
        }
    }

    /**
     * This can't be cached for extended periods of time, as its common (and very likely) the
     * uploads on a particular commit will change over time, and we need to be able to respond
     * to that.
     */
    public function isCachable(): bool
    {
        return false;
    }
}
