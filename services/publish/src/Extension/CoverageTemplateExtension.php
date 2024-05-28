<?php

namespace App\Extension;

use App\Extension\Function\CoverageReportFunction;
use App\Extension\Function\EventFunction;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CoverageTemplateExtension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        /**
         * @psalm-suppress InvalidArgument
         *
         * The signature is incorrect as the Container Loader in the twig environment will automatically
         * call the non-static method on an instance of the class.
         */
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
