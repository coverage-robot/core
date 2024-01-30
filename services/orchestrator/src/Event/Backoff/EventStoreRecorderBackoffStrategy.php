<?php

namespace App\Event\Backoff;

use App\Exception\OutOfOrderEventException;
use Exception;
use Override;
use STS\Backoff\Backoff;
use STS\Backoff\Strategies\LinearStrategy;

/**
 * A ready-made backoff strategy specifically for ensuring that event state changes are peristed into the
 * event store.
 *
 * This handles scenarios where the event store is being written to, and there is another contentious write
 * occurring at the same time for the same event, which therefore invalidates the state change.
 *
 * The retry interval is: 0ms, 500ms, 500ms
 */
final class EventStoreRecorderBackoffStrategy implements BackoffStrategyInterface
{
    private Backoff $backoff;

    public function __construct()
    {
        $this->backoff = new Backoff(
            maxAttempts: 2,
            strategy: new LinearStrategy(500),
            useJitter: true,
            decider: static function (
                int $attempt,
                int $maxAttempts,
                ?bool $result,
                ?Exception $exception = null
            ): bool {
                if ($exception instanceof OutOfOrderEventException) {
                    // Theres no point in re-trying this, as the event is out of order (i.e.
                    // a newer event has already been recorded)
                    return false;
                }

                return ($attempt <= $maxAttempts) && (!$result || $exception instanceof \Exception);
            }
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function run(callable $callback): mixed
    {
        return $this->backoff->run($callback);
    }

    #[Override]
    public function getBackoffStrategy(): Backoff
    {
        return $this->backoff;
    }
}
