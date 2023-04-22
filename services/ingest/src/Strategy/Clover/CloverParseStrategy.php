<?php

namespace App\Strategy\Clover;

use App\Enum\CoverageFormatEnum;
use App\Enum\LineTypeEnum;
use App\Exception\ParseException;
use App\Model\FileCoverage;
use App\Model\LineCoverage;
use App\Model\ProjectCoverage;
use App\Strategy\ParseStrategyInterface;
use XMLReader;

class CloverParseStrategy implements ParseStrategyInterface
{
    private const PROJECT = 'project';
    private const FILE = 'file';
    private const LINE = 'line';

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

    public function parse(string $content): ProjectCoverage
    {
        libxml_use_internal_errors(true);

        if (!$this->supports($content)) {
            throw ParseException::notSupportedException();
        }

        $reader = $this->buildXmlReader($content);
        $project = new ProjectCoverage(CoverageFormatEnum::CLOVER);

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

    private function handleNode(ProjectCoverage $coverage, XMLReader $reader): ProjectCoverage
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

                $coverage->addFileCoverage(new FileCoverage($path));
                break;
            case self::LINE:
                $files = $coverage->getFileCoverage();

                end($files)->addLineCoverage(
                    new LineCoverage(
                        $this->convertLineType($reader->getAttribute('type')),
                        intval($reader->getAttribute('num')),
                        $reader->getAttribute('name'),
                        intval($reader->getAttribute('count')),
                        intval($reader->getAttribute('complexity')),
                        floatval($reader->getAttribute('crap')),
                    )
                );
                break;
        }

        return $coverage;
    }

    private function convertLineType(?string $type): LineTypeEnum
    {
        return match ($type) {
            'stmt' => LineTypeEnum::STATEMENT,
            'cond' => LineTypeEnum::CONDITION,
            'method' => LineTypeEnum::METHOD,
            default => throw ParseException::lineTypeParseException($type ?? 'NULL')
        };
    }
}
