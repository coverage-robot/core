<?php

namespace App\Model;

use Stringable;

class ReportComparison implements Stringable
{
    public function __construct(
        private readonly ReportInterface $baseReport,
        private readonly ReportInterface $headReport
    ) {
    }

    /**
     * The report that is the base of the comparison.
     *
     * This is generally the report on the BASE of a PR using the parent commit
     * history.
     */
    public function getBaseReport(): ReportInterface
    {
        return $this->baseReport;
    }

    /**
     * The report that is at the head of the comparison.
     *
     * This is usually the head of a PR.
     */
    public function getHeadReport(): ReportInterface
    {
        return $this->headReport;
    }

    /**
     * The change in coverage percentage between the base and head reports.
     */
    public function getCoverageChange(): float
    {
        // Use the unrounded percentage to calculate the change so that its closest
        // to the actual change before rounding.
        $coverageChange = $this->headReport->getCoveragePercentage(false) -
            $this->baseReport->getCoveragePercentage(false);

        return round($coverageChange, 2);
    }

    public function __toString(): string
    {
        return sprintf(
            'ReportComparison#%s-%s',
            (string) $this->baseReport,
            (string) $this->headReport
        );
    }
}
