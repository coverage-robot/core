<?php

namespace App\Query\Result;

use Packages\Contracts\Line\LineState;
use Packages\Contracts\Line\LineType;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

class LineCoverageQueryResult implements QueryResultInterface
{
    /**
     * @param LineType[] $types
     */
    public function __construct(
        #[Assert\NotBlank]
        private readonly string $fileName,
        #[Assert\GreaterThanOrEqual(1)]
        private readonly int $lineNumber,
        private readonly LineState $state,
        private readonly bool $containsMethod,
        private readonly bool $containsBranch,
        private readonly bool $containsStatement,
        #[Assert\GreaterThanOrEqual(0)]
        private readonly int $totalBranches,
        #[Assert\GreaterThanOrEqual(0)]
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
    #[Assert\NotBlank]
    public function getTypes(): array
    {
        return [
            ...($this->containsMethod ? [LineType::METHOD] : []),
            ...($this->containsBranch ? [LineType::BRANCH] : []),
            ...($this->containsStatement ? [LineType::STATEMENT] : []),
        ];
    }

    public function containsMethod(): bool
    {
        return $this->containsMethod;
    }

    public function containsBranch(): bool
    {
        return $this->containsBranch;
    }

    public function containsStatement(): bool
    {
        return $this->containsStatement;
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
