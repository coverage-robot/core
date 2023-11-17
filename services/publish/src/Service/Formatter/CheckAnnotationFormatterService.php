<?php

namespace App\Service\Formatter;

use Packages\Message\PublishableMessage\PublishableCheckAnnotationMessage;
use Packages\Models\Enum\LineState;

class CheckAnnotationFormatterService
{
    public function formatTitle(PublishableCheckAnnotationMessage $annotationMessage): string
    {
        return sprintf(
            '%s Line',
            $annotationMessage->getLineState() !== LineState::UNCOVERED ? 'Covered' : 'Uncovered'
        );
    }

    public function format(PublishableCheckAnnotationMessage $annotationMessage): string
    {
        return sprintf(
            'This line is %s by a test.',
            $annotationMessage->getLineState() !== LineState::UNCOVERED ? 'covered' : 'not covered'
        );
    }
}
