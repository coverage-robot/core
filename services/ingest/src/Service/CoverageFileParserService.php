<?php

namespace App\Service;

use App\Exception\ParseException;
use App\Model\Coverage;
use App\Strategy\ParseStrategyInterface;
use Override;
use Packages\Contracts\Provider\Provider;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class CoverageFileParserService implements CoverageFileParserServiceInterface
{
    public function __construct(
        #[AutowireIterator('app.parser_strategy')]
        private readonly iterable $parserStrategies,
        private readonly LoggerInterface $parseStrategyLogger
    ) {
    }

    /**
     * @inheritDoc
     * @throws ParseException
     */
    #[Override]
    public function parse(
        Provider $provider,
        string $owner,
        string $repository,
        string $projectRoot,
        string $coverageFile
    ): Coverage {
        foreach ($this->parserStrategies as $parserStrategy) {
            if (!$parserStrategy instanceof ParseStrategyInterface) {
                $this->parseStrategyLogger->critical(
                    'Strategy does not implement the correct interface.',
                    [
                        'strategy' => $parserStrategy::class
                    ]
                );

                continue;
            }

            if (!$parserStrategy->supports($coverageFile)) {
                $this->parseStrategyLogger->info(
                    sprintf(
                        'Not parsing using %s, as it does not support content',
                        $parserStrategy::class
                    )
                );
                continue;
            }

            return $parserStrategy->parse(
                $provider,
                $owner,
                $repository,
                $projectRoot,
                $coverageFile
            );
        }

        throw new ParseException('No strategy found which supports coverage file content.');
    }
}
