<?php

namespace App\Tests\Client;

use App\Client\BigQueryClient;
use PHPUnit\Framework\TestCase;

class BigQueryClientTest extends TestCase
{
    public function testTableIsCorrectlyConstructed(): void
    {
        $client = new BigQueryClient();

        $this->assertEquals('mock-project.mock-dataset.mock-table', $client->getTable());
    }

    public function testDatasetIsCorrectlyConstructed(): void
    {
        $client = new BigQueryClient();

        $this->assertEquals('mock-dataset', $client->getEnvironmentDataset()->id());
    }
}
