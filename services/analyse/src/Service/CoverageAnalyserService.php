<?php

namespace App\Service;

use App\Model\CachedPublishableCoverageData;
use App\Model\PublishableCoverageDataInterface;
use Packages\Models\Model\Upload;

class CoverageAnalyserService
{
    public function __construct(private readonly QueryService $queryService)
    {
    }

    public function analyse(Upload $upload): PublishableCoverageDataInterface
    {
        return new CachedPublishableCoverageData(
            $this->queryService,
            $upload
        );
    }
}
