<?php

namespace App\Event\Backoff;

use Exception;

interface BackoffStrategyInterface
{
    /**
     * Run a callable, and retry it if a failure occurs, based on a configured backoff strategy.
     *
     * @throws Exception
     */
    public function run(callable $callback): mixed;
}
