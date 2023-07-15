<?php

namespace App\Service;

use App\Model\CachedPublishableCoverageData;
use App\Model\PublishableCoverageDataInterface;
use Packages\Models\Model\Upload;

class CoverageAnalyserService
{
    public function __construct(
        private readonly QueryService $queryService,
        private readonly DiffParserService $diffParser,
        private readonly CarryforwardTagService $carryforwardTagService
    ) {
    }

    public function analyse(Upload $upload): PublishableCoverageDataInterface
    {
        return new CachedPublishableCoverageData(
            $this->queryService,
            $this->diffParser,
            $this->carryforwardTagService,
            $upload
        );
    }
}
