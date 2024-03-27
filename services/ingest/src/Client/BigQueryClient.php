<?php

namespace App\Client;

use App\Enum\EnvironmentVariable;
use Google\Cloud\BigQuery\Dataset;
use Override;
use Packages\Contracts\Environment\EnvironmentServiceInterface;

final class BigQueryClient extends \Google\Cloud\BigQuery\BigQueryClient implements BigQueryClientInterface
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

    #[Override]
    public function getEnvironmentDataset(): Dataset
    {
        return $this->dataset(
            $this->environmentService->getVariable(EnvironmentVariable::BIGQUERY_ENVIRONMENT_DATASET)
        );
    }

    #[Override]
    public function getTable(): string
    {
        return sprintf(
            '%s.%s.%s',
            $this->environmentService->getVariable(EnvironmentVariable::BIGQUERY_PROJECT),
            $this->environmentService->getVariable(EnvironmentVariable::BIGQUERY_ENVIRONMENT_DATASET),
            $this->environmentService->getVariable(EnvironmentVariable::BIGQUERY_LINE_COVERAGE_TABLE)
        );
    }
}
