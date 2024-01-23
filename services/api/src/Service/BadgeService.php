<?php

namespace App\Service;

use Cog\SvgFont\FontList;
use Cog\Unicode\UnicodeString;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

class BadgeService
{
    final public const BADGE_LABEL = 'coverage';

    final public const NO_COVERGAGE_PERCENTAGE_VALUE = 'unknown';

    final public const FONT_FILE_NAME = 'dejavu-sans';

    final public const FONT_FILE = 'badges/' . self::FONT_FILE_NAME . '.svg';

    final public const FONT_SIZE = 11;

    public function __construct(
        private readonly Environment $twig,
        #[Autowire(value: '%twig.default_path%')]
        private readonly string $twigDefaultPath
    ) {
    }

    /**
     * Fully render a coverage badge, using the coverage percentage as the value.
     */
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
     * Build an appropriate hex color code using a coverage percentage.
     *
     * This works on a scale of red to green, using the percentage as the point of reference.
     *
     * So, 0% is fully red, and 100% is fully green, anything in between is a mix of both.
     */
    public function getHexForCoveragePercentage(float $percentage): string
    {
        $b = 0;
        if ($percentage < 50) {
            $r = 255;
            $g = round(5.1 * $percentage);
        } else {
            $r = round(510 - 5.1 * $percentage);
            $g = 255;
        }

        $h = $r * 0x10000 + $g * 0x100 + $b;

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
