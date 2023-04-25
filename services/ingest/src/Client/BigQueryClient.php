<?php

namespace App\Client;

use Google\Cloud\BigQuery\Dataset;

class BigQueryClient extends \Google\Cloud\BigQuery\BigQueryClient
{
    private const PROJECT = 'coverage-384615';

    private const SERVICE_ACCOUNT_KEY = __DIR__ . '/../../config/bigquery.json';

    private const DATASET = 'line_analytics';

    public function __construct(array $config = [])
    {
        parent::__construct(
            [
                'projectId' => self::PROJECT,
                'keyFilePath' => self::SERVICE_ACCOUNT_KEY
            ] + $config
        );
    }

    public function getLineAnalyticsDataset(): Dataset
    {
        return $this->dataset(self::DATASET);
    }
}
