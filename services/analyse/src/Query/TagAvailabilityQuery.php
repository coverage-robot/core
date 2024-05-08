<?php

namespace App\Query;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\TagAvailabilityCollectionQueryResult;
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

final class TagAvailabilityQuery implements QueryInterface
{
    use UploadTableAwareTrait;
    use ParameterAwareTrait;

    /**
     * The maximum days we'll consider a tag as being available for.
     *
     * This influences how long we look back in the commit history when carrying
     * forward tags from older commits, and reduces the total result size when returned.
     */
    private const int MAXIMUM_TAG_AGE_DAYS = 120;

    public function __construct(
        private readonly SerializerInterface&DenormalizerInterface $serializer,
        private readonly EnvironmentServiceInterface $environmentService
    ) {
    }

    #[Override]
    public function getQuery(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        $maximumTagAge = self::MAXIMUM_TAG_AGE_DAYS;

        return <<<SQL
        WITH availability AS (
            SELECT
                commit,
                tag,
                ARRAY_AGG(totalLines) as successfullyUploadedLines,
                ARRAY_AGG(STRING(ingestTime)) as ingestTimes
            FROM
                `{$table}`
            WHERE
                provider = {$this->getAlias(QueryParameter::PROVIDER)}
                AND owner = {$this->getAlias(QueryParameter::OWNER)}
                AND repository = {$this->getAlias(QueryParameter::REPOSITORY)}
                -- Only include uploads on tags which are recent. That way we can avoid permanently
                -- looking for tags deep in the commit history which are obsolete/no longer uploaded.
                AND ingestTime > CAST(TIMESTAMP_SUB(CURRENT_TIMESTAMP(), INTERVAL {$maximumTagAge} DAY) as DATETIME)
            GROUP BY
                commit,
                tag
        )
        SELECT
            availability.tag as tagName,
            ARRAY_AGG(
                STRUCT(
                    commit as `commit`,
                    tag as name,
                    successfullyUploadedLines as successfullyUploadedLines,
                    ingestTimes as ingestTimes
                )
            ) as carryforwardTags,
        FROM
            availability
        GROUP BY
            availability.tag
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
    public function parseResults(QueryResults $results): TagAvailabilityCollectionQueryResult
    {
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
