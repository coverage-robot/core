<?php

namespace App\Tests\Mock;

use App\Event\Backoff\ReadyToFinaliseBackoffStrategy;

/**
 * A fake backoff strategy used for confirming if all jobs are finished, and the coverage is
 * ready to finalise, which doesn't actually backoff at all.
 *
 * This is useful for tests which don't care about the wait time, and would just prefer to run
 * the callback immediately, and repeatedly.
 */
class FakeReadyToFinaliseBackoffStrategy extends ReadyToFinaliseBackoffStrategy
{
    public function __construct()
    {
        parent::__construct();

        // Set the capped wait time to 0ms, so that the callback is run immediately without any wait time
        // in between. This ensures that the strategy is still run as close to production as possible (i.e. the same
        // retires, the same decider, etc), just without the need for the test to hang around waiting for the time
        // to elapse.
        $this->backoff->setWaitCap(0);
    }
}
