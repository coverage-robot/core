<?php

namespace App\Query\Result;

use Packages\Models\Enum\LineState;

class LineCoverageQueryResult implements QueryResultInterface
{
    public function __construct(
        private readonly string $fileName,
        private readonly int $lineNumber,
        private readonly LineState $state
    ) {
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    public function getState(): LineState
    {
        return $this->state;
    }
}
