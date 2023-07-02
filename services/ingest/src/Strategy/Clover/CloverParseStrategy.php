<?php

namespace App\Strategy\Clover;

use App\Exception\ParseException;
use App\Service\PathFixingService;
use App\Strategy\ParseStrategyInterface;
use LibXMLError;
use Packages\Models\Enum\CoverageFormat;
use Packages\Models\Model\Coverage;
use Packages\Models\Model\File;
use Packages\Models\Model\Line\BranchCoverage;
use Packages\Models\Model\Line\MethodCoverage;
use Packages\Models\Model\Line\StatementCoverage;
use Psr\Log\LoggerInterface;
use XMLReader;

class CloverParseStrategy implements ParseStrategyInterface
{
    private const PROJECT = 'project';
    private const FILE = 'file';
    private const LINE = 'line';

    private const STATEMENT = 'stmt';
    private const METHOD = 'method';
    private const CONDITION = 'cond';

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
        libxml_use_internal_errors(true);

        $reader = $this->buildXmlReader($content);
        if (!$reader->read()) {
            $this->parseStrategyLogger->error('Unable to read first line of Clover file.');
            return false;
        }

        while ($reader->read()) {
            if (!$reader->isValid()) {
                $this->parseStrategyLogger->error(
                    sprintf('Received %s errors when validating Clover file.', count(libxml_get_errors())),
                    [
                        'errors' => array_map(
                            static fn(LibXMLError $error) => [
                                'code' => $error->code,
                                'message' => $error->message,
                                'line' => $error->line,
                                'column' => $error->column
                            ],
                            libxml_get_errors()
                        )
                    ]
                );

                libxml_clear_errors();
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
        libxml_use_internal_errors(true);

        if (!$this->supports($content)) {
            $this->parseStrategyLogger->critical('Parse method called for content which is not supported.');
            throw ParseException::notSupportedException();
        }

        $reader = $this->buildXmlReader($content);
        $project = new Coverage(CoverageFormat::CLOVER, $projectRoot);

        while ($reader->read()) {
            if ($reader->nodeType == XMLReader::END_ELEMENT) {
                // We don't want to parse the node if it's a closing tag, as the XML
                // reader will let the parser use the element with all the properties
                // from the opening tags, and that will cause duplicate files to be tracked.
                continue;
            }

            $project = $this->handleNode($project, $reader);
        }

        return $project;
    }

    protected function buildXmlReader(string $content): XMLReader
    {
        /** @var XMLReader|false $reader */
        $reader = XMLReader::XML($content);

        if ($reader instanceof XMLReader) {
            $reader->setSchema(__DIR__ . '/schema.xsd');
            return $reader;
        }

        throw new ParseException('Unable to build XML reader.');
    }

    private function handleNode(Coverage $coverage, XMLReader $reader): Coverage
    {
        switch ($reader->name) {
            case self::PROJECT:
                $timestamp = $reader->getAttribute('timestamp');
                if (!is_numeric($timestamp)) {
                    break;
                }

                $coverage->setGeneratedAt((int)$reader->getAttribute('timestamp'));
                break;
            case self::FILE:
                $path = $reader->getAttribute('path') ?? $reader->getAttribute('name');

                if ($path === null) {
                    break;
                }

                $path = $this->pathFixingService->removePathRoot($path, $coverage->getRoot());

                $coverage->addFile(new File($path));
                break;
            case self::LINE:
                $files = $coverage->getFiles();

                $type = $reader->getAttribute('type');
                $lineNumber = (int)$reader->getAttribute('num');
                $lineHits = (int)$reader->getAttribute('count');

                end($files)->setLineCoverage(
                    match ($type) {
                        self::METHOD => new MethodCoverage($lineNumber, $lineHits, $reader->getAttribute('name') ?? ''),
                        self::STATEMENT => new StatementCoverage($lineNumber, $lineHits),
                        self::CONDITION => new BranchCoverage(
                            $lineNumber,
                            $lineHits,
                            [
                                0 => (int)$reader->getAttribute('falsecount'),
                                1 => (int)$reader->getAttribute('truecount')
                            ]
                        ),
                        default => throw ParseException::lineTypeParseException($type ?? 'NULL')
                    }
                );
                break;
        }

        return $coverage;
    }
}
