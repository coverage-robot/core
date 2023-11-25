<?php

namespace App\Event\Backoff;

use App\Exception\OutOfOrderEventException;
use Exception;
use STS\Backoff\Backoff;

/**
 * A ready-made backoff strategy specifically for ensuring that event state changes are peristed into the
 * event store.
 *
 * This handles scenarios where the event store is being written to, and there is another contentious write
 * occurring at the same time for the same event, which therefore invalidates the state change.
 */
class EventStoreRecorderBackoffStrategy implements BackoffStrategyInterface
{
    protected Backoff $backoff;

    public function __construct()
    {
        $this->backoff = new Backoff(
            maxAttempts: 3,
            useJitter: true,
            decider: function (
                int $attempt,
                int $maxAttempts,
                ?bool $result,
                ?Exception $exception = null
            ) {
                if ($exception instanceof OutOfOrderEventException) {
                    // Theres no point in re-trying this, as the event is out of order (i.e.
                    // a newer event has already been recorded)
                    return false;
                }

                return ($attempt <= $maxAttempts) && (!$result || $exception);
            }
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
