<?php

declare(strict_types=1);

namespace App\Query;

use App\Client\BigQueryClient;
use App\Enum\EnvironmentVariable;
use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\TagAvailabilityCollectionQueryResult;
use App\Query\Trait\ParameterAwareTrait;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Override;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class TagAvailabilityQuery implements QueryInterface
{
    use ParameterAwareTrait;

    public function __construct(
        private readonly SerializerInterface&DenormalizerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly EnvironmentServiceInterface $environmentService,
        private readonly BigQueryClient $bigQueryClient
    ) {
    }

    #[Override]
    public function getQuery(?QueryParameterBag $parameterBag = null): string
    {
        $uploadTable = $this->bigQueryClient->getTable(
            $this->environmentService->getVariable(
                EnvironmentVariable::BIGQUERY_UPLOAD_TABLE
            )
        );

        return <<<SQL
        WITH availability AS (
            SELECT
                commit,
                tag,
                ARRAY_AGG(totalLines) as successfullyUploadedLines,
                ARRAY_AGG(STRING(ingestTime)) as ingestTimes
            FROM
                `{$uploadTable}`
            WHERE
                projectId = {$this->getAlias(QueryParameter::PROJECT_ID)}
                AND commit IN UNNEST({$this->getAlias(QueryParameter::COMMIT)})
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
    public function getNamedQueries(?QueryParameterBag $parameterBag = null): string
    {
        return '';
    }

    #[Override]
    public function getQueryParameterConstraints(): array
    {
        return [
            QueryParameter::PROJECT_ID->value => [
                new Assert\Type(type: 'string'),
                new Assert\Uuid(versions: [Assert\Uuid::V7_MONOTONIC])
            ],
            QueryParameter::COMMIT->value => [
                new Assert\Sequentially([
                    new Assert\Type(type: 'array'),
                    new Assert\Count(min: 1),
                    new Assert\All([
                        new Assert\Type(type: 'string'),
                        new Assert\Regex(pattern: '/^[a-f0-9]{40}$/')
                    ])
                ])
            ],
        ];
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

        $errors = $this->validator->validate($results);

        if (count($errors) > 0) {
            throw QueryException::invalidResult($results, $errors);
        }

        return $results;
    }
}
