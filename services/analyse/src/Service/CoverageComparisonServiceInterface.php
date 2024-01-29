<?php

namespace App\Service;

use App\Model\CoverageReportComparison;
use App\Model\CoverageReportInterface;
use Packages\Contracts\Event\EventInterface;

interface CoverageComparisonServiceInterface
{
    /**
     * Generate a comparison report for the given head waypoint and event.
     *
     * This works out (using the head waypoint) which is the most suitable base waypoint
     * to compare against, and then generates a comparison report using the analyser.
     */
    public function getComparisonForCoverageReport(
        CoverageReportInterface $headReport,
        EventInterface $event
    ): ?CoverageReportComparison;
}
