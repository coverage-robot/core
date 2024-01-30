<?php

namespace App\Client;

use Google\Cloud\BigQuery\Dataset;
use Google\Cloud\BigQuery\JobConfigurationInterface;
use Google\Cloud\BigQuery\QueryJobConfiguration;
use Google\Cloud\BigQuery\QueryResults;

interface BigQueryClientInterface
{
    /**
     * @return QueryResults
     */
    public function runQuery(JobConfigurationInterface $query, array $options = []);

    /**
     * @return QueryJobConfiguration
     */
    public function query(string $query, array $options = []);

    public function getEnvironmentDataset(): Dataset;

    public function getTable(?string $tableName = null): string;
}
