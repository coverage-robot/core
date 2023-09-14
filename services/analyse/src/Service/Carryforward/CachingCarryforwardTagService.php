<?php

namespace App\Service\Carryforward;

use App\Exception\QueryException;
use Packages\Models\Model\Event\EventInterface;
use Packages\Models\Model\Event\Upload;
use Packages\Models\Model\Tag;
use WeakMap;

class CachingCarryforwardTagService implements CarryforwardTagServiceInterface
{
    /**
     * @var WeakMap<Upload, Tag[]>
     */
    private WeakMap $cache;

    public function __construct(
        private readonly CarryforwardTagService $carryforwardTagService
    ) {
        /**
         * @var WeakMap<Upload, Tag[]>
         */
        $this->cache = new WeakMap();
    }

    /**
     * @throws QueryException
     */
    public function getTagsToCarryforward(EventInterface $event): array
    {
        if (isset($this->cache[$event])) {
            return $this->cache[$event];
        }

        return ($this->cache[$event] = $this->carryforwardTagService->getTagsToCarryforward($event));
    }
}
