<?php

namespace App\Tests\Query;

use App\Query\QueryInterface;
use App\Query\TotalUploadsQuery;

class TotalUploadsQueryTest extends AbstractQueryTestCase
{
    public static function getExpectedQueries(): array
    {
        return [
            <<<SQL
            SELECT
                COUNT(DISTINCT uploadId) as totalUploads
            FROM
                `mock-table`
            WHERE
                commit = 'mock-commit' AND
                owner = 'mock-owner' AND
                repository = 'mock-repository'
            SQL
        ];
    }

    public function getQueryClass(): QueryInterface
    {
        return new TotalUploadsQuery();
    }
}
