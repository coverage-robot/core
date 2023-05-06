<?php

namespace App\Model\Line;

use App\Enum\LineTypeEnum;

class BranchCoverage extends AbstractLineCoverage
{
    /**
     * @param int $lineNumber
     * @param int $lineHits
     * @param array<array-key, int> $branchHits
     */
    public function __construct(
        int $lineNumber,
        int $lineHits = 0,
        private array $branchHits = []
    ) {
        parent::__construct($lineNumber, $lineHits);
    }

    public function addToBranchHits(int $branchNumber, int $branchesTaken): void
    {
        $this->branchHits[$branchNumber] = ($this->branchHits[$branchNumber] ?? 0) + $branchesTaken;
    }

    /**
     * Get the branches hit during tests.
     *
     * It's important to note that the branch is only partially covered if any of the branches of logic **have
     * not** been run at least once.
     *
     * We cannot check if line hits is equal to branches taken because it's possible that a branch without an
     * else (for example, a condition for bailing early) will result in less branches hit than line hits.
     *
     * @return int[]
     */
    public function getBranchHits(): array
    {
        return $this->branchHits;
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
                'partial' => array_filter(
                        $this->getBranchHits(),
                        static fn(int $hits) => $hits === 0
                    ) !== [],
                'branchHits' => $this->getBranchHits()
            ]
        );
    }
}
