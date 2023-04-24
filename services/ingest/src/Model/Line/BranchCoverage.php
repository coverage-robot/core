<?php

namespace App\Model\Line;

use App\Enum\LineTypeEnum;

class BranchCoverage extends AbstractLineCoverage
{
    /**
     * @param int $lineNumber
     * @param int $lineHits
     * @param array<array-key, int> $branchesTaken
     */
    public function __construct(
        int $lineNumber,
        int $lineHits = 0,
        private array $branchesTaken = []
    ) {
        parent::__construct($lineNumber, $lineHits);
    }

    public function addToBranchesHit(int $branchNumber, int $branchesTaken): void
    {
        $this->branchesTaken[$branchNumber] = ($this->branchesTaken[$branchNumber] ?? 0) + $branchesTaken;
    }

    public function isPartiallyCovered(): bool
    {
        // The branch is only partially covered if any of the branches **have not**
        // been run at least once. We cannot check if line hits is equal to branches taken
        // because it's possible that a branch without an else (for example, a condition
        // for bailing early) will result in less branches hit than line hits.
        return !empty(
        array_filter(
            $this->branchesTaken,
            static fn(int $branchesTaken) => $branchesTaken === 0
        )
        );
    }

    public function getBranchesTaken(): array
    {
        return $this->branchesTaken;
    }

    public function getType(): LineTypeEnum
    {
        return LineTypeEnum::BRANCH;
    }

    public function jsonSerialize(): array
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'branchesTaken' => $this->getBranchesTaken(),
                'isPartiallyCovered' => $this->isPartiallyCovered(),
            ]
        );
    }
}
