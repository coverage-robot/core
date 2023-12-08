<?php

namespace App\Strategy\Lcov;

use App\Exception\ParseException;
use App\Service\PathFixingService;
use App\Strategy\ParseStrategyInterface;
use OutOfBoundsException;
use Packages\Models\Enum\CoverageFormat;
use Packages\Models\Model\Coverage;
use Packages\Models\Model\File;
use Packages\Models\Model\Line\Branch;
use Packages\Models\Model\Line\Method;
use Packages\Models\Model\Line\Statement;
use Psr\Log\LoggerInterface;

class LcovParseStrategy implements ParseStrategyInterface
{
    private const string FILE = 'SF';

    private const string LINE_DATA = 'DA';

    private const string BRANCH_DATA = 'BRDA';

    private const string FUNCTION = 'FN';

    private const string FUNCTION_DATA = 'FNDA';

    private const string LINE_STRUCTURE = '/^(?<type>\w+):(?<data>.*)$/';

    private const array COVERAGE_DATA_VALIDATION = [
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

    final public const string END_OF_RECORD_MARKER = 'end_of_record';

    public function __construct(
        private readonly LoggerInterface $parseStrategyLogger,
        private readonly PathFixingService $pathFixingService
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
            if ($record === '' || $record === self::END_OF_RECORD_MARKER) {
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
    public function parse(string $projectRoot, string $content): Coverage
    {
        if (!$this->supports($content)) {
            throw ParseException::notSupportedException();
        }

        $records = preg_split('/\n|\r\n?/', $content);

        $coverage = new Coverage(CoverageFormat::LCOV, $projectRoot);

        foreach ($records as $record) {
            $record = trim($record);

            // Skip empty lines and end-of-record markers
            if ($record === '' || $record === self::END_OF_RECORD_MARKER) {
                continue;
            }

            preg_match(self::LINE_STRUCTURE, $record, $matches);

            $coverage = $this->handleLine($coverage, $matches['type'], $matches['data']);
        }

        return $coverage;
    }

    private function handleLine(Coverage $coverage, string $type, string $data): Coverage
    {
        $files = $coverage->getFiles();

        /** @var File $latestFile */
        $latestFile = end($files);
        preg_match($this->getLineValidation($type), $data, $extractedData);

        switch ($type) {
            case self::FILE:
                $path = $this->pathFixingService->removePathRoot($data, $coverage->getRoot());

                $coverage->addFile(new File($path));
                break;
            case self::LINE_DATA:
                $latestFile->setLine(
                    new Statement(
                        (int)$extractedData['lineNumber'],
                        (int)$extractedData['lineHits'],
                    )
                );
                break;
            case self::FUNCTION:
            case self::FUNCTION_DATA:
                try {
                    $line = $latestFile->getLine($extractedData['name']);

                    $latestFile->setLine(
                        new Method(
                            $line->getLineNumber(),
                            (int)$extractedData['lineHits'] ?: $line->getLineHits(),
                            $extractedData['name']
                        )
                    );
                } catch (OutOfBoundsException) {
                    $latestFile->setLine(
                        new Method(
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
                    $line = $latestFile->getLine($lineNumber);

                    if ($line instanceof Branch) {
                        $line->addToBranchHits((int)$extractedData['branchNumber'], (int)$extractedData['branchHits']);
                        break;
                    }

                    // The line we already have tracked is not a branch (it wont be when running through the
                    // individual line data), meaning we should convert it to a branch now we officially know its
                    // type isn't a simple statement
                    $latestFile->setLine(
                        new Branch(
                            $line->getLineNumber(),
                            $line->getLineHits(),
                            [
                                (int)$extractedData['branchNumber'] => (int)$extractedData['branchHits']
                            ]
                        )
                    );
                } catch (OutOfBoundsException) {
                    // No coverage been tracked for this branch yet, meaning we should set it up
                    $latestFile->setLine(
                        new Branch(
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

    /**
     * @return non-empty-string
     */
    public function getLineValidation(string $type): string
    {
        if (!array_key_exists($type, self::COVERAGE_DATA_VALIDATION)) {
            throw ParseException::notSupportedException();
        }

        return sprintf('/%s/', self::COVERAGE_DATA_VALIDATION[$type]);
    }
}
