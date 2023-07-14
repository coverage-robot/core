<?php

namespace App\Query;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\IntegerQueryResult;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Packages\Models\Model\Upload;

class TotalUploadsQuery implements QueryInterface
{
    public function getQuery(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        return <<<SQL
        SELECT
            COUNT(DISTINCT uploadId) as totalUploads
        FROM
            `$table`
        WHERE
            commit = '{$parameterBag->get(QueryParameter::UPLOAD)->getCommit()}' AND
            owner = '{$parameterBag->get(QueryParameter::UPLOAD)->getOwner()}' AND
            repository = '{$parameterBag->get(QueryParameter::UPLOAD)->getRepository()}'
        SQL;
    }

    public function getNamedQueries(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        return '';
    }

    /**
     * @throws GoogleException
     * @throws QueryException
     */
    public function parseResults(QueryResults $results): IntegerQueryResult
    {
        if (!$results->isComplete()) {
            throw new QueryException('Query was not complete when attempting to parse results.');
        }

        /** @var array $row */
        $row = $results->rows()
            ->current();

        if (is_int($row['totalUploads'])) {
            return IntegerQueryResult::from($row['totalUploads']);
        }

        throw QueryException::typeMismatch(gettype($row['totalUploads']), 'int');
    }

    public function validateParameters(?QueryParameterBag $parameterBag = null): void
    {
        if (
            !$parameterBag ||
            !$parameterBag->has(QueryParameter::UPLOAD) ||
            !($parameterBag->get(QueryParameter::UPLOAD) instanceof Upload)
        ) {
            throw QueryException::invalidParameters(QueryParameter::UPLOAD);
        }
    }
}
