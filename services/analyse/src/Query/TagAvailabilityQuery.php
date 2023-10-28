<?php

namespace App\Query;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\TagAvailabilityCollectionQueryResult;
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

class TagAvailabilityQuery implements QueryInterface
{
    use UploadTableAwareTrait;
    use ScopeAwareTrait;

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
        SELECT
          tag as tagName,
          ARRAY_AGG(commit) as availableCommits,
        FROM
          `{$table}`
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

    public function validateParameters(?QueryParameterBag $parameterBag = null): void
    {
        if (!$parameterBag) {
            throw new QueryException(
                sprintf('Query %s requires parameters to be provided.', self::class)
            );
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
     * This query can't be cached, as it doesnt use any discernible parameters which will
     * ensure the cached query is still up to date.
     */
    public function isCachable(): bool
    {
        return false;
    }

    /**
     * @throws ExceptionInterface
     * @throws GoogleException
     * @throws QueryException
     */
    public function parseResults(QueryResults $results): TagAvailabilityCollectionQueryResult
    {
        if (!$results->isComplete()) {
            throw new QueryException('Query was not complete when attempting to parse results.');
        }

        $row = $results->rows();

        return $this->serializer->denormalize(
            ['tagAvailability' => iterator_to_array($row)],
            TagAvailabilityCollectionQueryResult::class,
            'array'
        );
    }
}
