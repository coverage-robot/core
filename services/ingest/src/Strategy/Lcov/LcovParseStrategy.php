<?php

namespace App\Strategy\Lcov;

use App\Exception\ParseException;
use App\Strategy\ParseStrategyInterface;
use OutOfBoundsException;
use Packages\Models\Enum\CoverageFormat;
use Packages\Models\Model\File;
use Packages\Models\Model\Line\BranchCoverage;
use Packages\Models\Model\Line\MethodCoverage;
use Packages\Models\Model\Line\StatementCoverage;
use Packages\Models\Model\Project;
use Psr\Log\LoggerInterface;

class LcovParseStrategy implements ParseStrategyInterface
{
    private const FILE = 'SF';
    private const LINE_DATA = 'DA';

    private const BRANCH_DATA = 'BRDA';
    private const FUNCTION = 'FN';
    private const FUNCTION_DATA = 'FNDA';

    private const LINE_STRUCTURE = '/^(?<type>\w+):(?<data>.*)$/';

    private const COVERAGE_DATA_VALIDATION = [
        'TN' => '.*$',
        self::FILE => '.+$',
        self::FUNCTION => '(?<lineNumber>\d+),(?<name>.+)$',
        self::FUNCTION_DATA => '(?<lineHits>\d+),(?<name>.+)$',
        'FNF' => '\d+$',
        'FNH' => '\d+$',
        self::LINE_DATA => '(?<lineNumber>\d+),(?<lineHits>\d+)$',
        'LH' => '\d+$',
        'LF' => '\d+$',
        self::BRANCH_DATA => '(?<lineNumber>\d+),\d+,(?<branchNumber>\d+),(?<branchHits>\d+)$',
        'BRF' => '.+$',
        'BRH' => '\d+$'
    ];

    public const END_OF_RECORD_MARKER = 'end_of_record';

    public function __construct(
        private readonly LoggerInterface $parseStrategyLogger
    ) {
    }

    /**
     * @inheritDoc
     */
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
            if (!preg_match(self::LINE_STRUCTURE, $record, $matches)) {
                $this->parseStrategyLogger->error(
                    'Unable to validate structure of line in Lcov file.',
                    [
                        'line' => $record
                    ]
                );

                return false;
            }

            if (!preg_match($this->getLineValidation($matches['type']), $matches['data'])) {
                $this->parseStrategyLogger->error(
                    'Unable to validate data of line in Lcov file.',
                    [
                        'line' => $record
                    ]
                );

                return false;
            }
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function parse(string $projectRoot, string $content): Project
    {
        if (!$this->supports($content)) {
            throw ParseException::notSupportedException();
        }

        $records = preg_split('/\n|\r\n?/', $content);

        $project = new Project(CoverageFormat::LCOV, $projectRoot);

        foreach ($records as $record) {
            $record = trim($record);

            // Skip empty lines and end-of-record markers
            if (empty($record) || $record === self::END_OF_RECORD_MARKER) {
                continue;
            }

            preg_match(self::LINE_STRUCTURE, $record, $matches);

            $project = $this->handleLine($project, $matches['type'], $matches['data']);
        }

        return $project;
    }

    private function handleLine(Project $coverage, string $type, string $data): Project
    {
        $files = $coverage->getFiles();

        /** @var File $latestFile */
        $latestFile = end($files);
        preg_match($this->getLineValidation($type), $data, $extractedData);

        switch ($type) {
            case self::FILE:
                $path = $this->getRelativeFilePath($data, $coverage);

                $coverage->addFile(new File($path));
                break;
            case self::LINE_DATA:
                $latestFile->setLineCoverage(
                    new StatementCoverage(
                        (int)$extractedData['lineNumber'],
                        (int)$extractedData['lineHits'],
                    )
                );
                break;
            case self::FUNCTION:
            case self::FUNCTION_DATA:
                try {
                    $line = $latestFile->getSpecificLineCoverage($extractedData['name']);

                    $latestFile->setLineCoverage(
                        new MethodCoverage(
                            $line->getLineNumber(),
                            (int)$extractedData['lineHits'] ?: $line->getLineHits(),
                            $extractedData['name']
                        )
                    );
                } catch (OutOfBoundsException) {
                    $latestFile->setLineCoverage(
                        new MethodCoverage(
                            (int)$extractedData['lineNumber'],
                            0,
                            $extractedData['name']
                        )
                    );
                }
                break;
            case self::BRANCH_DATA:
                $lineNumber = $extractedData['lineNumber'];

                try {
                    $line = $latestFile->getSpecificLineCoverage($lineNumber);

                    if ($line instanceof BranchCoverage) {
                        $line->addToBranchHits((int)$extractedData['branchNumber'], (int)$extractedData['branchHits']);
                        break;
                    }

                    $latestFile->setLineCoverage(
                        new BranchCoverage(
                            $line->getLineNumber(),
                            $line->getLineHits(),
                            [
                                (int)$extractedData['branchNumber'] => (int)$extractedData['branchHits']
                            ]
                        )
                    );
                } catch (OutOfBoundsException) {
                    $latestFile->setLineCoverage(
                        new BranchCoverage(
                            (int)$lineNumber,
                            0,
                            [(int)$extractedData['branchNumber'] => (int)$extractedData['branchHits']]
                        )
                    );
                }
                break;
        }

        return $coverage;
    }

    public function getLineValidation(string $type): string
    {
        if (!array_key_exists($type, self::COVERAGE_DATA_VALIDATION)) {
            throw ParseException::notSupportedException();
        }

        return sprintf('/%s/', self::COVERAGE_DATA_VALIDATION[$type]);
    }

    private function getRelativeFilePath(string $path, Project $coverage): ?string
    {
        // Trim the root from the files path, so that we store each file path relative
        // to the project root
        if (substr($path, 0, strlen($coverage->getRoot())) == $coverage->getRoot()) {
            $path = substr($path, strlen($coverage->getRoot()));
        }

        return trim($path, '/');
    }
}
