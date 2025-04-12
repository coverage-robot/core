<?php

declare(strict_types=1);

namespace App\Service;

interface BadgeServiceInterface
{
    public const string BADGE_LABEL = 'coverage';

    public const string NO_COVERGAGE_PERCENTAGE_VALUE = 'unknown';

    public const string NO_PROJECT_FOUND_VALUE = 'not found';

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

    /**
     * Render the coverage badge with a "not found" message for badges which don't relate
     * to a project we know about.
     */
    public function renderNotFoundCoveragePercentageBadge(bool $includeIcon = true): string;
}
