<?php

namespace App\Query;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\TotalUploadsQueryResult;
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

final class TotalUploadsQuery implements QueryInterface
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
            COALESCE(ARRAY_AGG(uploadId), []) as successfulUploads,
            COALESCE(ARRAY_AGG(STRING(ingestTime)), []) as successfulIngestTimes,
            COALESCE(
                ARRAY_AGG(
                    STRUCT(
                        tag as name,
                        {$this->getAlias(QueryParameter::COMMIT)} as commit
                    )
                ),
                []
            ) as successfulTags
        FROM
            `{$table}`
        WHERE
            provider = {$this->getAlias(QueryParameter::PROVIDER)}
            AND owner = {$this->getAlias(QueryParameter::OWNER)}
            AND repository = {$this->getAlias(QueryParameter::REPOSITORY)}
            AND commit = {$this->getAlias(QueryParameter::COMMIT)}
        SQL;
    }

    #[Override]
    public function getNamedQueries(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        return '';
    }

    /**
     * @throws GoogleException
     * @throws QueryException
     * @throws ExceptionInterface
     */
    #[Override]
    public function parseResults(QueryResults $results): TotalUploadsQueryResult
    {
        /** @var array $row */
        $row = $results->rows()
            ->current();

        /** @var TotalUploadsQueryResult $results */
        $results = $this->serializer->denormalize(
            $row,
            TotalUploadsQueryResult::class,
            'array'
        );

        return $results;
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
            !$parameterBag->has(QueryParameter::COMMIT) ||
            (
                !is_array($parameterBag->get(QueryParameter::COMMIT)) &&
                !is_string($parameterBag->get(QueryParameter::COMMIT))
            ) ||
            ($parameterBag->get(QueryParameter::COMMIT) === [] || $parameterBag->get(QueryParameter::COMMIT) === '')
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
    #[Override]
    public function isCachable(): bool
    {
        return false;
    }
}
