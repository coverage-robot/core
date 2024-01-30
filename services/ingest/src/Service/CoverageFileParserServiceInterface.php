<?php

namespace App\Service;

use App\Exception\ParseException;
use App\Model\Coverage;
use App\Strategy\ParseStrategyInterface;
use Packages\Contracts\Provider\Provider;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

interface CoverageFileParserServiceInterface
{
    /**
     * Attempt to parse an arbitrary coverage file content using all supported parsing
     * strategies.
     *
     * @throws ParseException
     */
    public function parse(
        Provider $provider,
        string $owner,
        string $repository,
        string $projectRoot,
        string $coverageFile
    ): Coverage;
}
