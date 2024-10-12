<?php

namespace App\Client;

use Google\Cloud\BigQuery\Dataset;
use Google\Cloud\BigQuery\Job;
use Google\Cloud\BigQuery\JobConfigurationInterface;
use Google\Cloud\Core\Exception\GoogleException;

interface BigQueryClientInterface
{
    public function getEnvironmentDataset(): Dataset;

    public function getTable(): string;

    /**
     * **Synchronously** runs a job and waits for it to complete.
     *
     * `maxRetries` can be passed in using the options array to indicate the number
     * of retries to attempt before failing.
     *
     * The default is 100 retires.
     *
     * @return Job
     *
     * @throws GoogleException
     */
    public function runJob(JobConfigurationInterface $config, array $options = []);
}
