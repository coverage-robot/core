<?php

declare(strict_types=1);

namespace App\Query;

use App\Client\BigQueryClient;
use App\Enum\EnvironmentVariable;
use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\TotalUploadsQueryResult;
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

final class TotalUploadsQuery implements QueryInterface
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
        SELECT
            COALESCE(ARRAY_AGG(uploadId), []) as successfulUploads,
            COALESCE(ARRAY_AGG(STRING(ingestTime)), []) as successfulIngestTimes,
            COALESCE(
                ARRAY_AGG(
                    STRUCT(
                        tag as name,
                        [totalLines] as successfullyUploadedLines,
                        {$this->getAlias(QueryParameter::COMMIT)} as `commit`
                    )
                ),
                []
            ) as successfulTags
        FROM
            `{$uploadTable}`
        WHERE
            projectId = {$this->getAlias(QueryParameter::PROJECT_ID)}
            AND commit = {$this->getAlias(QueryParameter::COMMIT)}
        SQL;
    }

    #[Override]
    public function getNamedQueries(?QueryParameterBag $parameterBag = null): string
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

        $errors = $this->validator->validate($results);

        if (count($errors) > 0) {
            throw QueryException::invalidResult($results, $errors);
        }

        return $results;
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
                new Assert\AtLeastOneOf([
                    new Assert\Sequentially([
                        new Assert\Type(type: 'string'),
                        new Assert\Regex(pattern: '/^[a-f0-9]{40}$/')
                    ]),
                    new Assert\Sequentially([
                        new Assert\Type(type: 'array'),
                        new Assert\Count(min: 1),
                        new Assert\Sequentially([
                            new Assert\Type(type: 'string'),
                            new Assert\Regex(pattern: '/^[a-f0-9]{40}$/')
                        ])
                    ])
                ])
            ],
        ];
    }
}
