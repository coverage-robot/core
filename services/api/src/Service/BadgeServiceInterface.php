<?php

namespace App\Service;

interface BadgeServiceInterface
{
    public const BADGE_LABEL = 'coverage';

    public const NO_COVERGAGE_PERCENTAGE_VALUE = 'unknown';

    public const FONT_FILE_NAME = 'dejavu-sans';

    public const FONT_FILE = 'badges/' . self::FONT_FILE_NAME . '.svg';

    public const FONT_SIZE = 11;

    /**
     * Fully render a coverage badge, using the coverage percentage as the value.
     */
    public function renderCoveragePercentageBadge(
        ?float $coveragePercentage,
        bool $includeIcon = true
    ): string;
}
