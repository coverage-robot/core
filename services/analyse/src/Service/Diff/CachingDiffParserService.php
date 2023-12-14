<?php

namespace App\Service\Diff;

use App\Model\ReportWaypoint;
use Packages\Contracts\Event\EventInterface;
use WeakMap;

class CachingDiffParserService implements DiffParserServiceInterface
{
    /**
     * @var WeakMap<EventInterface, array<string, array<int, int>>>
     */
    private WeakMap $cache;

    public function __construct(
        private readonly DiffParserService $diffParserService
    ) {
        /**
         * @var WeakMap<EventInterface, array<string, array<int, int>>> $cache
         */
        $cache = new WeakMap();

        $this->cache = $cache;
    }

    public function get(EventInterface|ReportWaypoint $event): array
    {
        if (isset($this->cache[$event])) {
            return $this->cache[$event];
        }

        $this->cache[$event] = $this->diffParserService->get($event);

        return $this->cache[$event];
    }
}
