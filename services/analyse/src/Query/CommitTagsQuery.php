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
        $commitScope = self::getCommitScope($parameterBag);
        $repositoryScope = self::getRepositoryScope($parameterBag);

        // To avoid any race conditions, the only uploads which should be included are those _before_ the current
        // upload we're analysing. This is because we cannot guarantee the completeness of any coverage data which
        // is after what we're currently uploading.
        $ingestTimeScope = sprintf(
            'ingestTime <= "%s"',
            $parameterBag?->get(QueryParameter::UPLOAD)
                ->getIngestTime()
                ->format('Y-m-d H:i:s')
        );

        return <<<SQL
        SELECT
            commit,
            ARRAY_AGG(DISTINCT tag) as tags
        FROM
            `{$table}`
        WHERE
            {$commitScope} AND
            {$ingestTimeScope} AND
            {$repositoryScope}
        GROUP BY
            commit
        SQL;
    }

    public function getNamedQueries(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        return '';
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
