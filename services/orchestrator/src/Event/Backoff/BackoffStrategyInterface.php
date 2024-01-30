<?php

namespace App\Event\Backoff;

use Exception;
use STS\Backoff\Backoff;

interface BackoffStrategyInterface
{
    /**
     * Run a callable, and retry it if a failure occurs, based on a configured backoff strategy.
     *
     * @throws Exception
     */
    public function run(callable $callback): mixed;

    /**
     * Get the pre-configured backoff strategy.
     */
    public function getBackoffStrategy(): Backoff;
}
