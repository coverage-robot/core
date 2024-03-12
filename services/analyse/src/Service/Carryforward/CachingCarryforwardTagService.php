<?php

namespace App\Service\Carryforward;

use App\Model\CarryforwardTag;
use App\Model\ReportWaypoint;
use App\Trait\InMemoryCacheTrait;
use Override;
use Packages\Contracts\Tag\Tag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CachingCarryforwardTagService implements CarryforwardTagServiceInterface
{
    use InMemoryCacheTrait;

    private const string CACHE_METHOD_NAME = 'getTagsToCarryforward';

    private const string EXISTING_TAGS_CACHE_PARAM = 'existingTags';

    private const string RESULT_CACHE_PARAM = 'result';

    public function __construct(
        #[Autowire(service: CarryforwardTagService::class)]
        private readonly CarryforwardTagServiceInterface $carryforwardTagService
    ) {
    }

    /**
     * @param Tag[] $existingTags
     * @return CarryforwardTag[]
     */
    #[Override]
    public function getTagsToCarryforward(ReportWaypoint $waypoint, array $existingTags): array
    {
        $carryforwardTags = $this->lookupExistingValueInCache($waypoint, $existingTags);

        if ($carryforwardTags !== null) {
            return $carryforwardTags;
        }

        $carryforwardTags = $this->carryforwardTagService->getTagsToCarryforward(
            $waypoint,
            $existingTags
        );

        /**
         * @var array{existingTags: Tag[], result: CarryforwardTag[]}[] $cache
         */
        $cache = $this->getCacheValue(__FUNCTION__, $waypoint, []);

        $this->setCacheValue(
            __FUNCTION__,
            $waypoint,
            array_merge(
                $cache,
                [
                    [
                        self::EXISTING_TAGS_CACHE_PARAM => $existingTags,
                        self::RESULT_CACHE_PARAM => $carryforwardTags
                    ]
                ]
            )
        );

        return $carryforwardTags;
    }

    /**
     * Attempt a lookup on the cache to find an existing computed value for the given
     * waypoint and tags.
     *
     * @return CarryforwardTag[]|null
     */
    private function lookupExistingValueInCache(ReportWaypoint $waypoint, array $existingTags): ?array
    {
        /**
         * @var array{existingTags: Tag[], result: CarryforwardTag[]} $cacheValue
         */
        foreach ($this->getCacheValue(self::CACHE_METHOD_NAME, $waypoint, []) as $cacheValue) {
            if (
                array_udiff(
                    $cacheValue[self::EXISTING_TAGS_CACHE_PARAM],
                    $existingTags,
                    static fn(Tag $a, Tag $b): int => $a->getName() <=> $b->getName()
                )
            ) {
                continue;
            }

            return $cacheValue[self::RESULT_CACHE_PARAM];
        }

        return null;
    }
}
