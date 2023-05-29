<?php

namespace App\Model;

use App\Model\QueryResult\TotalLineCoverageQueryResult;
use App\Model\QueryResult\TotalTagCoverageQueryResult;

interface PublishableCoverageDataInterface
{
    public function getTotalUploads(): int;

    public function getTotalLines(): int;

    public function getAtLeastPartiallyCoveredLines(): int;

    public function getUncoveredLines(): int;

    public function getCoveragePercentage(): float;

    public function getLineCoverage(): TotalLineCoverageQueryResult;

    public function getTagCoverage(): TotalTagCoverageQueryResult;
}
