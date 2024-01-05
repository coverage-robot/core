<?php

namespace App\Model;

use Stringable;

class ReportComparison implements Stringable
{
    public function __construct(
        private readonly CoverageReportInterface $baseReport,
        private readonly CoverageReportInterface $headReport
    ) {
    }

    /**
     * The report that is the base of the comparison.
     *
     * This is generally the report on the BASE of a PR using the parent commit
     * history.
     */
    public function getBaseReport(): CoverageReportInterface
    {
        return $this->baseReport;
    }

    /**
     * The report that is at the head of the comparison.
     *
     * This is usually the head of a PR.
     */
    public function getHeadReport(): CoverageReportInterface
    {
        return $this->headReport;
    }

    /**
     * The change in coverage percentage between the base and head reports.
     */
    public function getCoverageChange(): float
    {
        return round(
            $this->headReport->getCoveragePercentage() - $this->baseReport->getCoveragePercentage(),
            2
        );
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
