<?php

namespace App\Service;

use App\Model\ReportInterface;
use App\Model\ReportWaypoint;
use App\Service\Carryforward\CachingCarryforwardTagService;
use App\Service\Carryforward\CarryforwardTagServiceInterface;
use App\Service\Diff\CachingDiffParserService;
use App\Service\Diff\DiffParserServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use WeakMap;

class CachingCoverageAnalyserService extends CoverageAnalyserService implements CoverageAnalyserServiceInterface
{
    /**
     * @var WeakMap<ReportWaypoint, ReportInterface>
     */
    private WeakMap $cache;

    public function __construct(
        #[Autowire(service: CachingQueryService::class)]
        QueryServiceInterface $queryService,
        #[Autowire(service: CachingDiffParserService::class)]
        DiffParserServiceInterface $diffParser,
        #[Autowire(service: CachingCarryforwardTagService::class)]
        CarryforwardTagServiceInterface $carryforwardTagService
    ) {
        parent::__construct($queryService, $diffParser, $carryforwardTagService);

        /**
         * @var WeakMap<ReportWaypoint, ReportInterface> $cache
         */
        $cache = new WeakMap();

        $this->cache = $cache;
    }

    public function analyse(ReportWaypoint $waypoint): ReportInterface
    {
        if (isset($this->cache[$waypoint])) {
            return $this->cache[$waypoint];
        }

        $this->cache[$waypoint] = parent::analyse($waypoint);

        return $this->cache[$waypoint];
    }
}
