<?php

namespace App\Query;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\CommitCollectionQueryResult;
use App\Query\Trait\ScopeAwareTrait;
use App\Query\Trait\UploadTableAwareTrait;
use App\Service\EnvironmentService;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Packages\Models\Enum\Provider;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class CommitSuccessfulTagsQuery implements QueryInterface
{
    use ScopeAwareTrait;
    use UploadTableAwareTrait;

    /**
     * @param SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer
     */
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly EnvironmentService $environmentService
    ) {
    }

    public function getQuery(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        $commitScope = self::getCommitScope($parameterBag);
        $repositoryScope = self::getRepositoryScope($parameterBag);

        return <<<SQL
        {$this->getNamedQueries($table, $parameterBag)}
        SELECT
            commit,
            ARRAY_AGG(
                STRUCT(
                    tag as name,
                    commit as commit
                )
            ) as tags,
        FROM
            `{$table}`
        WHERE
            {$commitScope} AND
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
     * @return CommitCollectionQueryResult
     * @throws GoogleException
     * @throws QueryException
     * @throws ExceptionInterface
     */
    public function parseResults(QueryResults $results): CommitCollectionQueryResult
    {
        if (!$results->isComplete()) {
            throw new QueryException('Query was not complete when attempting to parse results.');
        }

        $commits = $results->rows();

        return $this->serializer->denormalize(
            ['commits' => iterator_to_array($commits)],
            CommitCollectionQueryResult::class,
            'array'
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
     * The successful tags on a commit _could_ be cached (in theory). This is because,
     * generally speaking, this query will only be performed using older commits in the
     * commit tree, and any time a new commit is made, the query parameters will end up
     * changing.
     *
     * However, there is a use case where newer commits in the tree could still be receiving
     * uploads while we're doing processing, so therefore a hard cache would get in the
     * way of that.
     */
    public function isCachable(): bool
    {
        return false;
    }
}
