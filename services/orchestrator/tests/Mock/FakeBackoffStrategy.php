<?php

namespace App\Tests\Mock;

use App\Event\Backoff\BackoffStrategyInterface;
use App\Event\Backoff\EventStoreRecorderBackoffStrategy;
use Override;
use STS\Backoff\Backoff;

/**
 * A fake backoff strategy used for simulating backoff during writes to the event store.
 *
 * This is useful for tests which don't care about the wait time, and would just prefer to run
 * the callback immediately, and repeatedly.
 */
final class FakeBackoffStrategy implements BackoffStrategyInterface
{
    private BackoffStrategyInterface $backoff;

    /**
     * @param class-string<BackoffStrategyInterface> $desiredBackoffStrategyClass
     */
    public function __construct(string $desiredBackoffStrategyClass = EventStoreRecorderBackoffStrategy::class)
    {
        $this->backoff = new $desiredBackoffStrategyClass();

        // Set the capped wait time to 0ms, so that the callback is run immediately without any wait time
        // in between. This ensures that the strategy is still run as close to production as possible (i.e. the same
        // retires, the same decider, etc), just without the need for the test to hang around waiting for the time
        // to elapse.
        $this->backoff->getBackoffStrategy()
            ->setWaitCap(0);
    }

    #[Override]
    public function run(callable $callback): mixed
    {
        return $this->backoff->run($callback);
    }

    #[\Override]
    public function getBackoffStrategy(): Backoff
    {
        return $this->backoff->getBackoffStrategy();
    }
}
