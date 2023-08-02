<?php

namespace App\Client;

use Google\Cloud\BigQuery\Dataset;

class BigQueryClient extends \Google\Cloud\BigQuery\BigQueryClient
{
    private const SERVICE_ACCOUNT_KEY = __DIR__ . '/../../config/bigquery.json';

    public function __construct(array $config = [])
    {
        if (file_exists(self::SERVICE_ACCOUNT_KEY)) {
            // We only want to pre-provide the service account key in environments where theres is one provided (e.g.
            // _not_ in CI, or test environments).
            $config = [
                ...$config,
                'keyFilePath' => self::SERVICE_ACCOUNT_KEY
            ];
        }

        parent::__construct(
            [
                ...$config,
                'projectId' => $_ENV['BIGQUERY_PROJECT']
            ]
        );
    }

    public function getEnvironmentDataset(): Dataset
    {
        return $this->dataset($_ENV['BIGQUERY_ENVIRONMENT_DATASET']);
    }

    public function getTable(): string
    {
        return sprintf(
            '%s.%s.%s',
            $_ENV['BIGQUERY_PROJECT'],
            $_ENV['BIGQUERY_ENVIRONMENT_DATASET'],
            $_ENV['BIGQUERY_LINE_COVERAGE_TABLE']
        );
    }
}
