<?php

namespace App\Client;

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
}
