<?php

namespace App\Service\Carryforward;

use App\Exception\QueryException;
use Packages\Models\Model\Event\EventInterface;
use Packages\Models\Model\Tag;
use WeakMap;

class CachingCarryforwardTagService implements CarryforwardTagServiceInterface
{
    private const EXISTING_TAGS_CACHE_PARAM = 'existingTags';

    private const RESULT_CACHE_PARAM = 'result';

    /**
     * @var WeakMap<EventInterface, array{ existingTags: Tag[], result: Tag[] }[]>
     */
    private WeakMap $cache;

    public function __construct(
        private readonly CarryforwardTagService $carryforwardTagService
    ) {
        /**
         * @var WeakMap<EventInterface, array{ existingTags: Tag[], result: Tag[] }[]>
         */
        $this->cache = new WeakMap();
    }

    /**
     * @throws QueryException
     */
    public function getTagsToCarryforward(EventInterface $event, array $existingTags): array
    {
        if ($carryforwardTags = $this->lookupExistingValueInCache($event, $existingTags)) {
            return $carryforwardTags;
        }

        $carryforwardTags = $this->carryforwardTagService->getTagsToCarryforward(
            $event,
            $existingTags
        );

        $this->cache[$event] = array_merge(
            ($this->cache[$event] ?? []),
            [
                [
                    self::EXISTING_TAGS_CACHE_PARAM => $existingTags,
                    self::RESULT_CACHE_PARAM => $carryforwardTags
                ]
            ]
        );

        return $carryforwardTags;
    }

    /**
     * Attempt a lookup on the cache to find an existing computed value for the given
     * event and tags.
     *
     * @return Tag[]|null
     */
    private function lookupExistingValueInCache(EventInterface $event, array $existingTags): ?array
    {
        if (!isset($this->cache[$event])) {
            return null;
        }

        foreach ($this->cache[$event] as $cacheValue) {
            if (
                array_udiff(
                    $cacheValue[self::EXISTING_TAGS_CACHE_PARAM],
                    $existingTags,
                    static fn(Tag $a, Tag $b) => $a->getName() <=> $b->getName()
                )
            ) {
                continue;
            }

            return $cacheValue[self::RESULT_CACHE_PARAM];
        }

        return null;
    }
}
