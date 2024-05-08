<?php

namespace App\Query;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\UploadedTagsCollectionQueryResult;
use App\Query\Trait\ParameterAwareTrait;
use App\Query\Trait\UploadTableAwareTrait;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Override;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Provider\Provider;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class UploadedTagsQuery implements QueryInterface
{
    use UploadTableAwareTrait;
    use ParameterAwareTrait;

    public function __construct(
        private readonly SerializerInterface&DenormalizerInterface $serializer,
        private readonly EnvironmentServiceInterface $environmentService
    ) {
    }

    #[Override]
    public function getQuery(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        return <<<SQL
        SELECT
            DISTINCT tag as tagName
        FROM
            `{$table}`
        WHERE
            provider = {$this->getAlias(QueryParameter::PROVIDER)}
            AND owner = {$this->getAlias(QueryParameter::OWNER)}
            AND repository = {$this->getAlias(QueryParameter::REPOSITORY)}
        SQL;
    }

    #[Override]
    public function getNamedQueries(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        return '';
    }

    #[Override]
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
    #[Override]
    public function isCachable(): bool
    {
        return false;
    }

    /**
     * @throws ExceptionInterface
     * @throws GoogleException
     * @throws QueryException
     */
    #[Override]
    public function parseResults(QueryResults $results): UploadedTagsCollectionQueryResult
    {
        $row = $results->rows();

        /** @var UploadedTagsCollectionQueryResult $results */
        $results = $this->serializer->denormalize(
            ['uploadedTags' => iterator_to_array($row)],
            UploadedTagsCollectionQueryResult::class,
            'array'
        );

        return $results;
    }
}
