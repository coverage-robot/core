<?php

namespace App\Service;

use App\Model\CachingPublishableCoverageData;
use App\Model\PublishableCoverageDataInterface;
use App\Service\Carryforward\CarryforwardTagServiceInterface;
use App\Service\Diff\DiffParserServiceInterface;
use Packages\Contracts\Event\EventInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CoverageAnalyserService
{
    public function __construct(
        #[Autowire(service: \App\Service\CachingQueryService::class)]
        private readonly QueryServiceInterface $queryService,
        #[Autowire(service: \App\Service\Diff\CachingDiffParserService::class)]
        private readonly DiffParserServiceInterface $diffParser,
        #[Autowire(service: \App\Service\Carryforward\CachingCarryforwardTagService::class)]
        private readonly CarryforwardTagServiceInterface $carryforwardTagService
    ) {
    }

    public function analyse(EventInterface $event): PublishableCoverageDataInterface
    {
        return new CachingPublishableCoverageData(
            $this->queryService,
            $this->diffParser,
            $this->carryforwardTagService,
            $event
        );
    }
}
