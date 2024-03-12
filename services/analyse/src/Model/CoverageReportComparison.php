<?php

namespace App\Model;

use Override;
use Stringable;

final class CoverageReportComparison implements Stringable
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
        // Use the unrounded percentage to calculate the change so that its closest
        // to the actual change before rounding.
        $coverageChange = $this->headReport->getCoveragePercentage(false) -
            $this->baseReport->getCoveragePercentage(false);

        return round($coverageChange, 2);
    }

    /**
     * The change in the total number of uncovered lines between the base and head reports.
     *
     * For example:
     * 1. A change of 0, means the number of uncovered lines as not changed.
     * 2. A change of 10, means overall (including new lines, and removed lines) the codebase
     *    has 10 more uncovered lines that it did before.
     * 3. A change of -2, means overall (including new lines, and removed lines) the codebase
     *    has 2 less uncovered lines than it did before.
     */
    public function getUncoveredLinesChange(): int
    {
        return $this->headReport->getUncoveredLines() -
            $this->baseReport->getUncoveredLines();
    }

    #[Override]
    public function __toString(): string
    {
        return sprintf(
            'ReportComparison#%s-%s',
            (string) $this->baseReport,
            (string) $this->headReport
        );
    }
}
