<?php

namespace App\Service\Diff;

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

    public function get(Upload $upload): array
    {
        if (isset($this->cache[$upload])) {
            return $this->cache[$upload];
        }

        $this->cache[$upload] = $this->diffParserService->get($upload);

        return $this->cache[$upload];
    }
}
