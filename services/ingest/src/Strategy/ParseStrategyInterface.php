<?php

declare(strict_types=1);

namespace App\Strategy;

use App\Model\Coverage;
use Packages\Contracts\Provider\Provider;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.parser_strategy')]
interface ParseStrategyInterface
{
    /**
     * Check if this particular strategy is capable of handling an arbitrary
     * string, which is presumed to be _some type_ of coverage file.
     *
     * There is no particular specification as to **how** the file should be
     * confirmed as capable of being handled by a given strategy. But it can be assumed
     * that if this method returns true, the parser will do a best-effort attempt
     * to produce a valid model of the coverage data.
     *
     */
    public function supports(string $content): bool;

    /**
     * Parse an arbitrary string (which is presumed to be a coverage file) using a given
     * strategy.
     *
     */
    public function parse(
        Provider $provider,
        string $owner,
        string $repository,
        string $projectRoot,
        string $content
    ): Coverage;
}
