<?php

namespace App\Query\Result;

use App\Enum\QueryResult;

class FileCoverageQueryResult extends CoverageQueryResult
{
    public function __construct(
        private readonly string $fileName,
        readonly float $coveragePercentage,
        readonly int $lines,
        readonly int $covered,
        readonly int $partial,
        readonly int $uncovered
    ) {
        parent::__construct(
            $coveragePercentage,
            $lines,
            $covered,
            $partial,
            $uncovered
        );
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     * Since this implements the coverage query result base class, the type needs to
     * be overridden so that the serializer knows the correct discriminator value to use
     * when serializing/de-serializing this object.
     *
     * @see QueryResultInterface
     */
    public function getType(): string
    {
        return QueryResult::FILE_COVERAGE->value;
    }
}
