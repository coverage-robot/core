<?php

namespace App\Client;

use App\Enum\EnvironmentVariable;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Google\Cloud\Storage\StorageClient;

class GoogleCloudStorageClient extends StorageClient
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
}
