<?php

namespace App\Client;

use App\Enum\EnvironmentVariable;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Google\Cloud\BigQuery\Dataset;

class BigQueryClient extends \Google\Cloud\BigQuery\BigQueryClient
{
    private const SERVICE_ACCOUNT_KEY = __DIR__ . '/../../config/bigquery.json';

    public function __construct(
        private readonly EnvironmentServiceInterface $environmentService,
        array $config = []
    ) {
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
                'projectId' => $this->environmentService->getVariable(EnvironmentVariable::BIGQUERY_PROJECT)
            ]
        );
    }

    public function getEnvironmentDataset(): Dataset
    {
        return $this->dataset(
            $this->environmentService->getVariable(EnvironmentVariable::BIGQUERY_ENVIRONMENT_DATASET)
        );
    }

    public function getTable(?string $tableName = null): string
    {
        return sprintf(
            '%s.%s.%s',
            $this->environmentService->getVariable(EnvironmentVariable::BIGQUERY_PROJECT),
            $this->environmentService->getVariable(EnvironmentVariable::BIGQUERY_ENVIRONMENT_DATASET),
            $tableName ?? $this->environmentService->getVariable(EnvironmentVariable::BIGQUERY_LINE_COVERAGE_TABLE)
        );
    }
}
