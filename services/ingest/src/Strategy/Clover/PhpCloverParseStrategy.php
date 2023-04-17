<?php

namespace App\Strategy\Clover;

use XMLReader;

class PhpCloverParseStrategy extends AbstractCloverParseStrategy
{
    protected function buildXmlReader(string $content): XMLReader
    {
        $reader = XMLReader::XML($content);
        $reader->setSchema(__DIR__ . '/Schema/Php.xsd');

        return $reader;
    }
}
