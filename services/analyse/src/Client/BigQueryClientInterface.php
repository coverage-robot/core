<?php

namespace App\Client;

use Google\Cloud\BigQuery\Dataset;
use Google\Cloud\BigQuery\JobConfigurationInterface;

interface BigQueryClientInterface
{
    public function runQuery(JobConfigurationInterface $query, array $options = []);

    public function query($query, array $options = []);

    public function getEnvironmentDataset(): Dataset;

    public function getTable(?string $tableName = null): string;
}
