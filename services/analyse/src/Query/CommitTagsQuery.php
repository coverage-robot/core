<?php

namespace App\Query;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\CommitCollectionQueryResult;
use App\Query\Trait\ScopeAwareTrait;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Packages\Models\Enum\Provider;

class CommitTagsQuery implements QueryInterface
{
    use ScopeAwareTrait;

    public function getQuery(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        return <<<SQL
        {$this->getNamedQueries($table, $parameterBag)}
        SELECT
            *
        FROM
            tags
        WHERE
            allUploadsSuccessful = 1
        SQL;
    }

    public function getNamedQueries(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        $commitScope = self::getCommitScope($parameterBag);
        $repositoryScope = self::getRepositoryScope($parameterBag);

        return <<<SQL
        WITH uploads AS (
            SELECT
                commit,
                tag,
                IF (totalLines >= COUNT(uploadId), 1, 0) as isSuccessfulUpload
            FROM
                `{$table}`
            WHERE
                {$commitScope} AND
                {$repositoryScope}
            GROUP BY
                commit,
                uploadId,
                tag,
                totalLines
        ),
        tags AS (
            SELECT
                commit,
                ARRAY_AGG(tag) as tags,
                MIN(isSuccessfulUpload) as allUploadsSuccessful
            FROM
                uploads
            GROUP BY
                commit
        )
        SQL;
    }

    /**
     * @param QueryResults $results
     * @param QueryParameterBag|null $parameterBag
     * @throws GoogleException
     * @throws QueryException
     */
    public function parseResults(QueryResults $results): CommitCollectionQueryResult
    {
        if (!$results->isComplete()) {
            throw new QueryException('Query was not complete when attempting to parse results.');
        }

        /** @var array $commits */
        $commits = $results->rows();

        return CommitCollectionQueryResult::from($commits);
    }

    public function validateParameters(?QueryParameterBag $parameterBag = null): void
    {
        if (!$parameterBag) {
            throw new QueryException(
                sprintf('Query %s requires parameters to be provided.', self::class)
            );
        }

        if (!$parameterBag->has(QueryParameter::UPLOAD)) {
            throw QueryException::invalidParameters(QueryParameter::UPLOAD);
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
}
