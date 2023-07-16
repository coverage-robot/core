<?php

namespace App\Service\Carryforward;

use App\Exception\QueryException;
use Packages\Models\Model\Tag;
use Packages\Models\Model\Upload;
use WeakMap;

class CachingCarryforwardTagService implements CarryforwardTagServiceInterface
{
    /**
     * @var WeakMap<Upload, array<array-key, array<array-key, Tag>>>
     */
    private WeakMap $cache;

    public function __construct(
        private readonly CarryforwardTagService $carryforwardTagService
    ) {
        /**
         * @var WeakMap<Upload, array<array-key, array<array-key, Tag>>>
         */
        $this->cache = new WeakMap();
    }

    /**
     * @throws QueryException
     */
    public function getTagsToCarryforward(Upload $upload): array
    {
        if (isset($this->cache[$upload])) {
            return $this->cache[$upload];
        }

        return ($this->cache[$upload] = $this->carryforwardTagService->getTagsToCarryforward($upload));
    }
}
