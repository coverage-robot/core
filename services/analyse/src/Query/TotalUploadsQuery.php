<?php

namespace App\Query;

use App\Exception\QueryException;
use App\Model\QueryResult\IntegerQueryResult;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Packages\Models\Model\Upload;

class TotalUploadsQuery implements QueryInterface
{
    public function getQuery(string $table, Upload $upload): string
    {
        return <<<SQL
        SELECT
            COUNT(DISTINCT uploadId) as totalUploads
        FROM
            `$table`
        WHERE
            commit = '{$upload->getCommit()}' AND
            owner = '{$upload->getOwner()}' AND
            repository = '{$upload->getRepository()}'
        SQL;
    }

    public function getNamedQueries(string $table, Upload $upload): string
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
}
