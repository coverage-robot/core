<?php

declare(strict_types=1);

namespace App\Service;

use Cog\SvgFont\FontList;
use Cog\Unicode\UnicodeString;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

final class BadgeService implements BadgeServiceInterface
{
    public function __construct(
        private readonly Environment $twig,
        #[Autowire(value: '%twig.default_path%')]
        private readonly string $twigDefaultPath
    ) {
    }

    /**
     * Fully render a coverage badge, using the coverage percentage as the value.
     */
    #[Override]
    public function renderCoveragePercentageBadge(
        ?float $coveragePercentage,
        bool $includeIcon = true
    ): string {
        $font = FontList::ofFile($this->twigDefaultPath . '/' . self::FONT_FILE)
            ->getById($this->getFontFamily(self::FONT_FILE_NAME));

        $value = $coveragePercentage !== null ?
            sprintf(
                '%s%%',
                floor($coveragePercentage) !== $coveragePercentage ?
                    number_format($coveragePercentage, 2, '.', '') :
                    $coveragePercentage
            ) :
            self::NO_COVERGAGE_PERCENTAGE_VALUE;

        return $this->twig->render(
            'badges/badge.svg.twig',
            [
                'fontFamily' => $this->getFontFamily(self::FONT_FILE_NAME),
                'iconWidth' => $includeIcon ? 15 : 0,
                'labelWidth' => $font->computeStringWidth(
                    UnicodeString::of(self::BADGE_LABEL),
                    self::FONT_SIZE
                ),
                'valueWidth' => $font->computeStringWidth(
                    UnicodeString::of($value),
                    self::FONT_SIZE
                ),
                'label' => self::BADGE_LABEL,
                'value' => $value,
                'color' => $this->getHexForCoveragePercentage($coveragePercentage ?? 0),
            ]
        );
    }

    /**
     * Render the coverage badge with a "not found" message for badges which don't relate
     * to a project we know about.
     */
    #[Override]
    public function renderNotFoundCoveragePercentageBadge(bool $includeIcon = true): string
    {
        $font = FontList::ofFile($this->twigDefaultPath . '/' . self::FONT_FILE)
            ->getById($this->getFontFamily(self::FONT_FILE_NAME));

        $value = self::NO_PROJECT_FOUND_VALUE;

        return $this->twig->render(
            'badges/badge.svg.twig',
            [
                'fontFamily' => $this->getFontFamily(self::FONT_FILE_NAME),
                'iconWidth' => $includeIcon ? 15 : 0,
                'labelWidth' => $font->computeStringWidth(
                    UnicodeString::of(self::BADGE_LABEL),
                    self::FONT_SIZE
                ),
                'valueWidth' => $font->computeStringWidth(
                    UnicodeString::of($value),
                    self::FONT_SIZE
                ),
                'label' => self::BADGE_LABEL,
                'value' => $value,
                'color' => $this->getHexForCoveragePercentage(0),
            ]
        );
    }

    /**
     * Build an appropriate hex color code using a coverage percentage.
     *
     * This works on a scale of red to green, using the percentage as the point of reference.
     *
     * So, 0% is fully red, and 100% is fully green, anything in between is a mix of both.
     */
    private function getHexForCoveragePercentage(float $percentage): string
    {
        $b = 0.0;
        if ($percentage < 50) {
            $r = 255.0;
            $g = round(5.1 * $percentage);
        } else {
            $r = round(510.0 - 5.1 * $percentage);
            $g = 255.0;
        }

        $h = $r * 65536.0 + $g * 256.0 + $b;

        return substr('000000' . base_convert((string)$h, 10, 16), -6);
    }

    /**
     * Convert the font file into the font family name.
     */
    private function getFontFamily(string $fontFileName): string
    {
        return str_replace('-', ' ', $fontFileName);
    }
}
