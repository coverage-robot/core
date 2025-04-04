<?php

declare(strict_types=1);

namespace App\Tests\Client;

use App\Client\BigQueryClient;
use App\Enum\EnvironmentVariable;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use PHPUnit\Framework\TestCase;
use Packages\Contracts\Environment\Service;

final class BigQueryClientTest extends TestCase
{
    public function testTableIsCorrectlyConstructed(): void
    {
        $client = new BigQueryClient(
            MockEnvironmentServiceFactory::createMock(
                Environment::TESTING,
                Service::ANALYSE,
                [
                    EnvironmentVariable::BIGQUERY_PROJECT->value => 'mock-project',
                    EnvironmentVariable::BIGQUERY_ENVIRONMENT_DATASET->value => 'mock-dataset',
                    EnvironmentVariable::BIGQUERY_LINE_COVERAGE_TABLE->value => 'mock-line-coverage-table',
                ]
            )
        );

        $this->assertSame('mock-project.mock-dataset.mock-line-coverage-table', $client->getTable());
    }

    public function testDatasetIsCorrectlyConstructed(): void
    {
        $client = new BigQueryClient(
            MockEnvironmentServiceFactory::createMock(
                Environment::TESTING,
                Service::ANALYSE,
                [
                    EnvironmentVariable::BIGQUERY_PROJECT->value => 'mock-project',
                    EnvironmentVariable::BIGQUERY_ENVIRONMENT_DATASET->value => 'mock-dataset'
                ]
            )
        );

        $this->assertSame('mock-dataset', $client->getEnvironmentDataset()->id());
    }
}
