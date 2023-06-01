<?php

namespace App\Client;

use Google\Cloud\BigQuery\Dataset;

class BigQueryClient extends \Google\Cloud\BigQuery\BigQueryClient
{
    private const SERVICE_ACCOUNT_KEY = __DIR__ . '/../../config/bigquery.json';

    public function __construct(array $config = [])
    {
        parent::__construct(
            [
                'projectId' => $_ENV['BIGQUERY_PROJECT'],
                'keyFilePath' => self::SERVICE_ACCOUNT_KEY
            ] + $config
        );
    }

    public function getEnvironmentDataset(): Dataset
    {
        return $this->dataset($_ENV['BIGQUERY_ENVIRONMENT_DATASET']);
    }
}
