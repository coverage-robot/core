<?php

namespace App\Strategy\Clover;

use XMLReader;

class AgnosticCloverParseStrategy extends AbstractCloverParseStrategy
{
    protected function buildXmlReader(string $content): XMLReader
    {
        $reader = XMLReader::XML($content);
        $reader->setSchema(__DIR__ . '/Schema/Agnostic.xsd');

        return $reader;
    }
}
