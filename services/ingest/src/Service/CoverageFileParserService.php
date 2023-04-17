<?php

namespace App\Service;

use App\Model\ProjectCoverage;
use App\Strategy\Clover\AbstractCloverParseStrategy;
use App\Strategy\Clover\AgnosticCloverParseStrategy;
use App\Strategy\Clover\PhpCloverParseStrategy;
use App\Strategy\ParseStrategyInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class CoverageFileParserService implements ServiceSubscriberInterface
{
    public function __construct(
        private readonly ContainerInterface $container
    ) {
    }

    public function parse(string $coverageFile): ProjectCoverage
    {
        foreach (self::getSubscribedServices() as $strategy) {
            if (!is_subclass_of(ParseStrategyInterface::class, $strategy)) {
                throw new RuntimeException('Strategy does not implement the correct interface');
            }

            /** @var ParseStrategyInterface $parserStrategy */
            $parserStrategy = $this->container->get($strategy);

            if (!$parserStrategy->supports($coverageFile)) {
                continue;
            }

            return $parserStrategy->parse($coverageFile);
        }

        throw new RuntimeException('No strategy found which supports coverage file content');
    }

    public static function getSubscribedServices(): array
    {
        return [
            PhpCloverParseStrategy::class,
            AgnosticCloverParseStrategy::class
        ];
    }
}
