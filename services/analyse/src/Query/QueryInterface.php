<?php

namespace App\Query;

use App\Model\Upload;
use Google\Cloud\BigQuery\QueryResults;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.coverage_query')]
interface QueryInterface
{
    public function getNamedSubqueries(string $table, Upload $upload): string;

    public function getQuery(string $table, Upload $upload): string;

    public function parseResults(QueryResults $results): string|int|float|array;
}
