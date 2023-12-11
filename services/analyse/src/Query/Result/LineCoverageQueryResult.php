<?php

namespace App\Query\Result;

use Packages\Models\Enum\LineState;
use Packages\Models\Enum\LineType;
use Symfony\Component\Serializer\Attribute\Ignore;

class LineCoverageQueryResult implements QueryResultInterface
{
    /**
     * @param LineType[] $types
     */
    public function __construct(
        private readonly string $fileName,
        private readonly int $lineNumber,
        private readonly LineState $state,
        private readonly bool $containsMethod,
        private readonly bool $containsBranch,
        private readonly bool $containsStatement,
        private readonly int $totalBranches,
        private readonly int $coveredBranches,
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

    /**
     * Get all of the types of lines which have been defined for this line.
     *
     * A single line **can** contain coverage for multiple types of lines (i.e.
     * a statement could be on the same line as a function), so we're doing our
     * best here to account for a 0+ relationship between line coverage and
     * lines themselves.
     */

    #[Ignore]
    public function getTypes(): array
    {
        return [
            ...($this->containsMethod ? [LineType::METHOD] : []),
            ...($this->containsBranch ? [LineType::BRANCH] : []),
            ...($this->containsStatement ? [LineType::STATEMENT] : []),
        ];
    }

    /**
     * Get the total number of branches for this line.
     */
    public function getTotalBranches(): int
    {
        return $this->totalBranches;
    }

    /**
     * Get the number of covered branches for this line.
     */
    public function getCoveredBranches(): int
    {
        return $this->coveredBranches;
    }
}
