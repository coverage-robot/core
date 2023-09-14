<?php

namespace App\Service;

use App\Model\CachingPublishableCoverageData;
use App\Model\PublishableCoverageDataInterface;
use App\Service\Carryforward\CarryforwardTagServiceInterface;
use App\Service\Diff\DiffParserServiceInterface;
use Packages\Models\Model\Event\EventInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CoverageAnalyserService
{
    public function __construct(
        private readonly QueryService $queryService,
        #[Autowire(service: 'App\Service\Diff\CachingDiffParserService')]
        private readonly DiffParserServiceInterface $diffParser,
        #[Autowire(service: 'App\Service\Carryforward\CachingCarryforwardTagService')]
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
