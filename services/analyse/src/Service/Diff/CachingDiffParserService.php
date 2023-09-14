<?php

namespace App\Service\Diff;

use Packages\Models\Model\Event\EventInterface;
use Packages\Models\Model\Event\Upload;
use WeakMap;

class CachingDiffParserService implements DiffParserServiceInterface
{
    /**
     * @var WeakMap<Upload, int[][]>
     */
    private WeakMap $cache;

    public function __construct(
        private readonly DiffParserService $diffParserService
    ) {
        /**
         * @var WeakMap<Upload, int[][]>
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
