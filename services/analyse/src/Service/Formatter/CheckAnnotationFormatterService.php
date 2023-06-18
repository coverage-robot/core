<?php

namespace App\Service\Formatter;

use App\Model\QueryResult\LineCoverageQueryResult;
use Packages\Models\Enum\LineState;

class CheckAnnotationFormatterService
{
    public function format(LineCoverageQueryResult $line): string
    {
        return sprintf(
            'This line is %s by a test.',
            $line->getState() !== LineState::UNCOVERED ? 'covered' : 'not covered'
        );
    }
}
