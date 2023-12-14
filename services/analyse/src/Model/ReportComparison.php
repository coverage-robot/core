<?php

namespace App\Model;

class ReportComparison
{
    public function __construct(
        private readonly ReportInterface $baseReport,
        private readonly ReportInterface $headReport
    ) {
    }

    public function getBaseReport(): ReportInterface
    {
        return $this->baseReport;
    }

    public function getHeadReport(): ReportInterface
    {
        return $this->headReport;
    }

    public function getCoverageChange(): float
    {
        return $this->headReport->getCoveragePercentage() -
            $this->baseReport->getCoveragePercentage();
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
