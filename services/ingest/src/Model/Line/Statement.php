<?php

declare(strict_types=1);

namespace App\Model\Line;

use Override;
use Packages\Contracts\Line\LineType;

final class Statement extends AbstractLine
{
    #[Override]
    public function getType(): LineType
    {
        return LineType::STATEMENT;
    }
}
