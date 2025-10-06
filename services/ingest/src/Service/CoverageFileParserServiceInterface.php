<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ParseException;
use App\Model\Coverage;
use Packages\Contracts\Provider\Provider;

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
