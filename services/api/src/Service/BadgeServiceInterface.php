<?php

namespace App\Service;

interface BadgeServiceInterface
{
    public const string BADGE_LABEL = 'coverage';

    public const string NO_COVERGAGE_PERCENTAGE_VALUE = 'unknown';

    public const string FONT_FILE_NAME = 'dejavu-sans';

    public const string FONT_FILE = 'badges/' . self::FONT_FILE_NAME . '.svg';

    public const int FONT_SIZE = 11;

    /**
     * Fully render a coverage badge, using the coverage percentage as the value.
     */
    public function renderCoveragePercentageBadge(
        ?float $coveragePercentage,
        bool $includeIcon = true
    ): string;
}
