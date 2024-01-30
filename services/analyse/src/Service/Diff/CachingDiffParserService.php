<?php

namespace App\Service\Diff;

use App\Model\ReportWaypoint;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use WeakMap;

final class CachingDiffParserService implements DiffParserServiceInterface
{
    /**
     * @var WeakMap<ReportWaypoint, array<string, array<int, int>>>
     */
    private WeakMap $cache;

    public function __construct(
        #[Autowire(service: DiffParserService::class)]
        private readonly DiffParserServiceInterface $diffParserService
    ) {
        /**
         * @var WeakMap<ReportWaypoint, array<string, array<int, int>>> $cache
         */
        $cache = new WeakMap();

        $this->cache = $cache;
    }

    #[Override]
    public function get(ReportWaypoint $waypoint): array
    {
        if (isset($this->cache[$waypoint])) {
            return $this->cache[$waypoint];
        }

        $this->cache[$waypoint] = $this->diffParserService->get($waypoint);

        return $this->cache[$waypoint];
    }
}
