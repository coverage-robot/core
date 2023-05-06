<?php

namespace App\Query;

use App\Exception\QueryException;
use App\Model\Upload;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;

class TotalCommitUploadsQuery implements QueryInterface
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

    public function getNamedSubqueries(string $table, Upload $upload): string
    {
        return '';
    }

    /**
     * @throws GoogleException
     * @throws QueryException
     */
    public function parseResults(QueryResults $results): int
    {
        if (!$results->isComplete()) {
            throw new QueryException('Query was not complete when attempting to parse results.');
        }

        $rows = $results->rows();

        /** @var mixed|null $totalUploads */
        $totalUploads = $rows->current()['totalUploads'] ?? null;

        if (is_int($totalUploads)) {
            return $totalUploads;
        }

        throw QueryException::typeMismatch(gettype($totalUploads), "int");
    }
}
