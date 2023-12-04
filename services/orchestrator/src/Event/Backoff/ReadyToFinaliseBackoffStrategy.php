<?php

namespace App\Event\Backoff;

use STS\Backoff\Backoff;
use STS\Backoff\Strategies\PolynomialStrategy;

/**
 * A ready-made backoff strategy specifically for ensuring that all jobs have finished, and the finalised event
 * is ready to be persisted.
 *
 * This class uses a polynomial backoff algorithm to retry checking the event
 * store, in the case subsequent events are being written to the event store soon
 * after.
 *
 * The retry interval is: 0ms, 400ms, 900ms, 1600ms, 2500ms
 */
class ReadyToFinaliseBackoffStrategy implements BackoffStrategyInterface
{
    protected Backoff $backoff;

    public function __construct()
    {
        $this->backoff = new Backoff(
            maxAttempts: 5,
            strategy: new PolynomialStrategy(100, 2),
            decider: static fn (
                int $attempt,
                int $maxAttempts,
                ?bool $result
            ) => ($attempt <= $maxAttempts) && $result == true,
        );
    }

    /**
     * @inheritDoc
     */
    public function run(callable $callback): mixed
    {
        return $this->backoff->run($callback);
    }
}
