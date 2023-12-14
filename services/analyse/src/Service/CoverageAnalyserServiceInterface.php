<?php

namespace App\Service;

use App\Model\ReportComparison;
use App\Model\ReportInterface;
use App\Model\ReportWaypoint;
use Packages\Contracts\Event\EventInterface;

interface CoverageAnalyserServiceInterface
{
    public function getWaypointFromEvent(EventInterface $event): ReportWaypoint;

    public function analyse(ReportWaypoint $waypoint): ReportInterface;

    public function compare(ReportInterface $base, ReportInterface $head): ReportComparison;
}
