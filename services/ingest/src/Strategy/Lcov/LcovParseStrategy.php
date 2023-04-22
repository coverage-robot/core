<?php

namespace App\Strategy\Lcov;

use App\Enum\CoverageFormatEnum;
use App\Enum\LineTypeEnum;
use App\Exception\ParseException;
use App\Model\FileCoverage;
use App\Model\LineCoverage;
use App\Model\ProjectCoverage;
use App\Strategy\ParseStrategyInterface;

class LcovParseStrategy implements ParseStrategyInterface
{
    private const FILE = "SF";
    private const LINE = "DA";

    private const COVERAGE_DATA_VALIDATION = [
        'TN'   => '.*$',
        self::FILE   => '.+$',
        'FN'   => '.+$',
        'FNDA' => '\d+,.+$',
        'FNF'  => '\d+$',
        'FNH'  => '\d+$',
        self::LINE   => '\d+,\d+$',
        'LH'   => '\d+$',
        'LF'   => '\d+$',
        'BRDA' => '\d+,\d+,\d+$',
        'BRF'  => '.+$',
        'BRH'  => '\d+$'
    ];

    public const END_OF_RECORD_MARKER = "end_of_record";

    public function supports(string $content): bool
    {
        $records = preg_split('/\n|\r\n?/', $content);

        foreach ($records as $record) {
            $record = trim($record);

            // Skip empty lines and end-of-record markers
            if (empty($record) || $record === self::END_OF_RECORD_MARKER) {
                continue;
            }

            // Match the record type and its data
            if (!preg_match('/^(?<type>\w+):(?<data>.*)$/', $record, $matches)) {
                return false;
            }

            if (!preg_match($this->getLineValidation($matches["type"]), $matches["data"])) {
                return false;
            }
        }

        return true;
    }

    public function parse(string $content): ProjectCoverage
    {
        if (!$this->supports($content)) {
            throw ParseException::notSupportedException();
        }

        $records = preg_split('/\n|\r\n?/', $content);

        $projectCoverage = new ProjectCoverage(CoverageFormatEnum::LCOV);

        foreach ($records as $record) {
            $record = trim($record);

            // Skip empty lines and end-of-record markers
            if (empty($record) || $record === self::END_OF_RECORD_MARKER) {
                continue;
            }

            preg_match('/^(?<type>\w+):(?<data>.*)$/', $record, $matches);

            $projectCoverage = $this->handleLine($projectCoverage, $matches["type"], $matches["data"]);
        }

        return $projectCoverage;
    }

    private function handleLine(ProjectCoverage $coverage, string $type, string $data): ProjectCoverage
    {
        switch ($type) {
            case self::FILE:
                $coverage->addFileCoverage(new FileCoverage($data));
                break;
            case self::LINE:
                $files = $coverage->getFileCoverage();

                $data = explode(",", $data);

                end($files)->addLineCoverage(
                    new LineCoverage(
                        LineTypeEnum::UNKNOWN,
                        (int)$data[0],
                        null,
                        (int)$data[1],
                        0,
                        0,
                    )
                );
                break;
        }

        return $coverage;
    }

    public function getLineValidation(string $type): string
    {
        if (!array_key_exists($type, self::COVERAGE_DATA_VALIDATION)) {
            throw ParseException::notSupportedException();
        }

        return sprintf("/%s/", self::COVERAGE_DATA_VALIDATION[$type]);
    }
}
