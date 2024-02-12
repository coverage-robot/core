<?php

namespace App\Extension;

use App\Extension\Function\AnnotationFunction;
use App\Extension\Function\EventFunction;
use App\Extension\Function\MetricsFunction;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CoverageTemplateExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                MetricsFunction::getFunctionName(),
                [MetricsFunction::class, 'call'],
                MetricsFunction::getOptions()
            ),
            new TwigFunction(
                EventFunction::getFunctionName(),
                [EventFunction::class, 'call'],
                EventFunction::getOptions()
            ),
            new TwigFunction(
                AnnotationFunction::getFunctionName(),
                [AnnotationFunction::class, 'call'],
                AnnotationFunction::getOptions()
            )
        ];
    }
}
