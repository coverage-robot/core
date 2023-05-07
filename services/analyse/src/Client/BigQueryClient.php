<?php

namespace App\Client;

class BigQueryClient extends \Google\Cloud\BigQuery\BigQueryClient
{
    public const PROJECT = 'coverage-384615';

    private const SERVICE_ACCOUNT_KEY = __DIR__ . '/../../config/bigquery.json';

    public const DATASET = 'line_analytics';

    public const TABLE = 'lines';

    public function __construct(array $config = [])
    {
        parent::__construct(
            [
                'projectId' => self::PROJECT,
                'keyFilePath' => self::SERVICE_ACCOUNT_KEY
            ] + $config
        );
    }

    public function getTable(): string
    {
        return sprintf('%s.%s.%s', BigQueryClient::PROJECT, BigQueryClient::DATASET, BigQueryClient::TABLE);
    }
}
