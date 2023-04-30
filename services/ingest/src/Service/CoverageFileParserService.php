<?php

namespace App\Service;

use App\Exception\ParseException;
use App\Model\Project;
use App\Strategy\ParseStrategyInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class CoverageFileParserService
{
    public function __construct(
        #[TaggedIterator('app.parser_strategy')]
        private readonly iterable $parserStrategies,
        private readonly LoggerInterface $parseStrategyLogger
    ) {
    }

    /**
     * Attempt to parse an arbitrary coverage file content using all supported parsing
     * strategies.
     *
     * @throws ParseException
     */
    public function parse(string $coverageFile): Project
    {
        foreach ($this->parserStrategies as $strategy) {
            if (!$strategy instanceof ParseStrategyInterface) {
                $this->parseStrategyLogger->critical(
                    'Strategy does not implement the correct interface.',
                    [
                        'strategy' => $strategy::class
                    ]
                );

                continue;
            }

            if (!$strategy->supports($coverageFile)) {
                continue;
            }

            return $strategy->parse($coverageFile);
        }

        throw new ParseException('No strategy found which supports coverage file content.');
    }
}
