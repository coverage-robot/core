<?php

namespace App\Model\QueryResult;

use App\Enum\LineStateEnum;
use App\Exception\QueryException;

class LineCoverageQueryResult implements QueryResultInterface
{
    public function __construct(
        private readonly string $fileName,
        private readonly int $lineNumber,
        private readonly LineStateEnum $state
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
                LineStateEnum::from((string)$row['state'])
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

    public function getState(): LineStateEnum
    {
        return $this->state;
    }
}
