<?php

namespace App\Service\Diff;

use Packages\Contracts\Event\EventInterface;
use WeakMap;

class CachingDiffParserService implements DiffParserServiceInterface
{
    /**
     * @var WeakMap<EventInterface, int[][]>
     */
    private WeakMap $cache;

    public function __construct(
        private readonly DiffParserService $diffParserService
    ) {
        /**
         * @var WeakMap<EventInterface, int[][]>
         */
        $this->cache = new WeakMap();
    }

    public function get(EventInterface $event): array
    {
        if (isset($this->cache[$event])) {
            return $this->cache[$event];
        }

        $this->cache[$event] = $this->diffParserService->get($event);

        return $this->cache[$event];
    }
}
