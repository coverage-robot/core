<?php

declare(strict_types=1);

namespace App\Query;

use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\LineCoverageQueryResult;
use App\Query\Result\QueryResultIterator;
use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Override;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class LineCoverageQuery extends AbstractLineCoverageQuery
{
    public function __construct(
        private readonly SerializerInterface&DenormalizerInterface $serializer,
        private readonly ValidatorInterface $validator,
        EnvironmentServiceInterface $environmentService,
        BigQueryClient $bigQueryClient
    ) {
        parent::__construct($environmentService, $bigQueryClient);
    }

    #[Override]
    public function getQuery(?QueryParameterBag $parameterBag = null): string
    {
        return <<<SQL
        {$this->getNamedQueries($parameterBag)}
        SELECT
            *
        FROM
            lines
        ORDER BY
            fileName ASC,
            lineNumber ASC
        SQL;
    }

    /**
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
                    LineCoverageQueryResult::class,
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
