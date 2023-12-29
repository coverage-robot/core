<?php

namespace App\Tests\Client;

use App\Client\BigQueryClient;
use App\Enum\EnvironmentVariable;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use PHPUnit\Framework\TestCase;

class BigQueryClientTest extends TestCase
{
    public function testTableIsCorrectlyConstructed(): void
    {
        $client = new BigQueryClient(
            MockEnvironmentServiceFactory::createMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::BIGQUERY_PROJECT->value => 'mock-project',
                    EnvironmentVariable::BIGQUERY_ENVIRONMENT_DATASET->value => 'mock-dataset',
                    EnvironmentVariable::BIGQUERY_LINE_COVERAGE_TABLE->value => 'mock-line-coverage-table',
                ]
            )
        );

        $this->assertEquals('mock-project.mock-dataset.mock-line-coverage-table', $client->getTable());
    }

    public function testDatasetIsCorrectlyConstructed(): void
    {
        $client = new BigQueryClient(
            MockEnvironmentServiceFactory::createMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::BIGQUERY_PROJECT->value => 'mock-project',
                    EnvironmentVariable::BIGQUERY_ENVIRONMENT_DATASET->value => 'mock-dataset'
                ]
            )
        );

        $this->assertEquals('mock-dataset', $client->getEnvironmentDataset()->id());
    }
}
