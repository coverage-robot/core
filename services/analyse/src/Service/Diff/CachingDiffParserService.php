<?php

namespace App\Service\Diff;

use App\Model\ReportWaypoint;
use App\Trait\InMemoryCacheTrait;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CachingDiffParserService implements DiffParserServiceInterface
{
    use InMemoryCacheTrait;

    public function __construct(
        #[Autowire(service: DiffParserService::class)]
        private readonly DiffParserServiceInterface $diffParserService
    ) {
    }

    #[Override]
    public function get(ReportWaypoint $waypoint): array
    {
        if ($this->hasCacheValue(__FUNCTION__, $waypoint)) {
            /**
             * @var array<string, array<int, int>>
             */
            return $this->getCacheValue(__FUNCTION__, $waypoint);
        }

        $results = $this->diffParserService->get($waypoint);
        $this->setCacheValue(__FUNCTION__, $waypoint, $results);

        return $results;
    }
}
