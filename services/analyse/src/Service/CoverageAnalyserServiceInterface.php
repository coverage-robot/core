<?php

namespace App\Service;

use App\Exception\ComparisonException;
use App\Model\ReportComparison;
use App\Model\ReportInterface;
use App\Model\ReportWaypoint;
use Packages\Contracts\Event\EventInterface;

interface CoverageAnalyserServiceInterface
{
    /**
     * Get a reporting waypoint for a realtime event which has occured.
     */
    public function getWaypointFromEvent(EventInterface $event): ReportWaypoint;

    /**
     * Build a coverage report for a particular waypoint.
     */
    public function analyse(ReportWaypoint $waypoint): ReportInterface;

    /**
     * Compare two (comparable) reports against each other.
     *
     * @throws ComparisonException
     */
    public function compare(ReportInterface $base, ReportInterface $head): ReportComparison;
}
