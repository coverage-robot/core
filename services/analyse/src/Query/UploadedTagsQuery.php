<?php

declare(strict_types=1);

namespace App\Query;

use App\Enum\EnvironmentVariable;
use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\QueryResultIterator;
use App\Query\Result\UploadedTagsQueryResult;
use App\Query\Trait\BigQueryTableAwareTrait;
use App\Query\Trait\ParameterAwareTrait;
use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Override;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UploadedTagsQuery implements QueryInterface
{
    use ParameterAwareTrait;
    use BigQueryTableAwareTrait;

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
        $uploadTable = $this->getTable(
            $this->environmentService->getVariable(
                EnvironmentVariable::BIGQUERY_UPLOAD_TABLE
            )
        );

        return <<<SQL
        SELECT
            DISTINCT tag as tagName
        FROM
            `{$uploadTable}`
        WHERE
            projectId = {$this->getAlias(QueryParameter::PROJECT_ID)}
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
        ];
    }

    /**
     * @throws ExceptionInterface
     * @throws GoogleException
     * @throws QueryException
     */
    #[Override]
    public function parseResults(QueryResults $results): QueryResultIterator
    {
        $totalRows = $results->info()['totalRows'];
        if (!is_numeric($totalRows)) {
            throw new QueryException(
                sprintf(
                    'Invalid total rows count when parsing results as iterator for %s: %s',
                    self::class,
                    (string)$totalRows
                )
            );
        }

        return new QueryResultIterator(
            $results->rows(['maxResults' => 200]),
            (int)$totalRows,
            function (array $row) {
                $row = $this->serializer->denormalize(
                    $row,
                    UploadedTagsQueryResult::class,
                    'json'
                );

                $errors = $this->validator->validate($row);

                if (count($errors) > 0) {
                    throw QueryException::invalidResult($row, $errors);
                }

                return $row;
            }
        );
    }
}
