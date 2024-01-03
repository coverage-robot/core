<?php

namespace App\Query;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\TagAvailabilityCollectionQueryResult;
use App\Query\Trait\ScopeAwareTrait;
use App\Query\Trait\UploadTableAwareTrait;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Provider\Provider;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class TagAvailabilityQuery implements QueryInterface
{
    use UploadTableAwareTrait;
    use ScopeAwareTrait;

    public function __construct(
        private readonly SerializerInterface&DenormalizerInterface $serializer,
        private readonly EnvironmentServiceInterface $environmentService
    ) {
    }

    public function getQuery(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        $repositoryScope = self::getRepositoryScope($parameterBag);

        return <<<SQL
        WITH availability AS (
            SELECT
                commit,
                tag,
                ARRAY_AGG(ingestTime) as ingestTimes
            FROM
                `{$table}`
            WHERE
                {$repositoryScope}
            GROUP BY
                commit,
                tag
        )
        SELECT
            availability.tag as tagName,
            ARRAY_AGG(
                STRUCT(
                    commit as commit,
                    tag as name,
                    ingestTimes as ingestTimes
                )
            ) as availableTags,
        FROM
            availability
        GROUP BY
            availability.tag
        SQL;
    }

    public function getNamedQueries(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        return '';
    }

    public function validateParameters(?QueryParameterBag $parameterBag = null): void
    {
        if (!$parameterBag instanceof QueryParameterBag) {
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

        /** @var TagAvailabilityCollectionQueryResult $results */
        $results = $this->serializer->denormalize(
            ['tagAvailability' => iterator_to_array($row)],
            TagAvailabilityCollectionQueryResult::class,
            'array'
        );

        return $results;
    }
}
