<?php

namespace App\Client;

use Google\Cloud\BigQuery\Dataset;
use Google\Cloud\BigQuery\Job;
use Google\Cloud\BigQuery\JobConfigurationInterface;

interface BigQueryClientInterface
{
    public function getEnvironmentDataset(): Dataset;

    public function getTable(): string;

    /**
     * @return Job
     */
    public function runJob(JobConfigurationInterface $config, array $options = []);
}
