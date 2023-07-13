<?php

namespace App\Service;

use App\Entity\Project;
use PUGX\Poser\Poser;

class BadgeService
{
    public const BADGE_LABEL = 'coverage';

    public function __construct(private readonly Poser $poser)
    {
    }

    public function getBadge(Project $project): string
    {
        return (string)$this->poser->generate(
            self::BADGE_LABEL,
            $project->getCoveragePercentage() !== null ?
                sprintf('%s%%', number_format(floatval($project->getCoveragePercentage()), 2)) :
                'unknown',
            $this->getHex($project->getCoveragePercentage() ?? 0),
            'flat'
        );
    }

    private function getHex(float $percentage): string
    {
        $b = 0;
        if ($percentage < 50) {
            $r = 255;
            $g = round(5.1 * $percentage);
        } else {
            $r = round(510 - 5.1 * $percentage);
            $g = 255;
        }

        $h = $r * 0x10000 + $g * 0x100 + $b * 0x1;

        return substr('000000' . base_convert((string)$h, 10, 16), -6);
    }
}
