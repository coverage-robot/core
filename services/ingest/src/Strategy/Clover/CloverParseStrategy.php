<?php

namespace App\Strategy\Clover;

use App\Enum\CoverageFormatEnum;
use App\Exception\ParseException;
use App\Model\File;
use App\Model\Line\BranchCoverage;
use App\Model\Line\MethodCoverage;
use App\Model\Line\StatementCoverage;
use App\Model\Project;
use App\Strategy\ParseStrategyInterface;
use XMLReader;

class CloverParseStrategy implements ParseStrategyInterface
{
    private const PROJECT = 'project';
    private const FILE = 'file';
    private const LINE = 'line';

    private const STATEMENT = 'stmt';
    private const METHOD = 'method';
    private const CONDITION = 'cond';

    public function supports(string $content): bool
    {
        libxml_use_internal_errors(true);

        $reader = $this->buildXmlReader($content);
        if (!$reader->read()) {
            return false;
        }

        while ($reader->read()) {
            if (!$reader->isValid()) {
                return false;
            }
        }

        return true;
    }

    public function parse(string $content): Project
    {
        libxml_use_internal_errors(true);

        if (!$this->supports($content)) {
            throw ParseException::notSupportedException();
        }

        $reader = $this->buildXmlReader($content);
        $project = new Project(CoverageFormatEnum::CLOVER);

        while ($reader->read()) {
            if ($reader->nodeType == XMLReader::END_ELEMENT) {
                // We don't want to parse if it's a closing tag as the XML reader will
                // let the parser use it as if it's the opening element, and that will
                // cause duplicate files to be tracked.
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

    private function handleNode(Project $coverage, XMLReader $reader): Project
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
