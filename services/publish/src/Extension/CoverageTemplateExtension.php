<?php

namespace App\Extension;

use App\Extension\Function\CoverageReportFunction;
use App\Extension\Function\EventFunction;
use App\Extension\Function\LineCommentFunction;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CoverageTemplateExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                CoverageReportFunction::getFunctionName(),
                [CoverageReportFunction::class, 'call'],
                CoverageReportFunction::getOptions()
            ),
            new TwigFunction(
                EventFunction::getFunctionName(),
                [EventFunction::class, 'call'],
                EventFunction::getOptions()
            )
        ];
    }
}
