<?php

namespace App\Service;

use App\Exception\ParseException;
use App\Model\Project;
use App\Strategy\ParseStrategyInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class CoverageFileParserService
{
    public function __construct(
        #[TaggedIterator('app.parser_strategy')]
        private readonly iterable $parserStrategies
    ) {
    }

    /**
     * @throws ParseException
     */
    public function parse(string $coverageFile): Project
    {
        foreach ($this->parserStrategies as $strategy) {
            if (!$strategy instanceof ParseStrategyInterface) {
                throw new ParseException('Strategy does not implement the correct interface.');
            }

            if (!$strategy->supports($coverageFile)) {
                continue;
            }

            return $strategy->parse($coverageFile);
        }

        throw new ParseException('No strategy found which supports coverage file content.');
    }
}
