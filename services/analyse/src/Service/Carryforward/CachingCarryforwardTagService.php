<?php

namespace App\Service\Carryforward;

use App\Model\ReportWaypoint;
use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Tag\Tag;
use WeakMap;

class CachingCarryforwardTagService implements CarryforwardTagServiceInterface
{
    private const string EXISTING_TAGS_CACHE_PARAM = 'existingTags';

    private const string RESULT_CACHE_PARAM = 'result';

    /**
     * @var WeakMap<EventInterface|ReportWaypoint, array{ existingTags: Tag[], result: Tag[] }[]>
     */
    private WeakMap $cache;

    public function __construct(
        private readonly CarryforwardTagService $carryforwardTagService
    ) {
        /**
         * @var WeakMap<EventInterface|ReportWaypoint, array{ existingTags: Tag[], result: Tag[] }[]> $cache
         */
        $cache = new WeakMap();

        $this->cache = $cache;
    }

    /**
     * @param Tag[] $existingTags
     * @return Tag[]
     */
    public function getTagsToCarryforward(ReportWaypoint $waypoint, array $existingTags): array
    {
        if ($carryforwardTags = $this->lookupExistingValueInCache($waypoint, $existingTags)) {
            return $carryforwardTags;
        }

        $carryforwardTags = $this->carryforwardTagService->getTagsToCarryforward(
            $waypoint,
            $existingTags
        );

        $this->cache[$waypoint] = array_merge(
            ($this->cache[$waypoint] ?? []),
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
     * waypoint and tags.
     *
     * @return Tag[]|null
     */
    private function lookupExistingValueInCache(ReportWaypoint $waypoint, array $existingTags): ?array
    {
        if (!isset($this->cache[$waypoint])) {
            return null;
        }

        foreach ($this->cache[$waypoint] as $cacheValue) {
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
