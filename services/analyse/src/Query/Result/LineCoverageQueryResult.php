<?php

namespace App\Query\Result;

use App\Exception\QueryException;
use Packages\Models\Enum\LineState;

class LineCoverageQueryResult implements QueryResultInterface
{
    public function __construct(
        private readonly string $fileName,
        private readonly int $lineNumber,
        private readonly LineState $state
    ) {
    }

    public static function from(array $row): self
    {
        if (
            is_string($row['fileName'] ?? null) &&
            is_int($row['lineNumber'] ?? null) &&
            is_string($row['state'] ?? null)
        ) {
            return new self(
                (string)$row['fileName'],
                (int)$row['lineNumber'],
                LineState::from((string)$row['state'])
            );
        }

        throw QueryException::invalidQueryResult();
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
