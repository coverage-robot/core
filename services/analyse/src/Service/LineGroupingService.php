<?php

namespace App\Service;

use App\Query\Result\LineCoverageQueryResult;
use DateTimeImmutable;
use Packages\Event\Model\EventInterface;
use Packages\Message\PublishableMessage\PublishableAnnotationInterface;
use Packages\Message\PublishableMessage\PublishableMissingCoverageAnnotationMessage;
use Packages\Message\PublishableMessage\PublishablePartialBranchAnnotationMessage;
use Packages\Models\Enum\LineState;
use Packages\Models\Enum\LineType;

class LineGroupingService
{
    /**
     * Group lines into annotations which can be published.
     *
     * This takes into account a number of scenarios:
     * 1. Blocks of missing coverage (i.e. grouping 10 sequential uncovered lines)
     * 2. Missing coverage within method definitions (i.e. grouping 5 sequential
     *    lines within a method which are uncovered)
     * 3. Partial branch coverage (i.e. annotating branches which are not fully covered)
     *
     * @param int[][] $diff
     * @param LineCoverageQueryResult[] $lineCoverage
     *
     * @return PublishableAnnotationInterface[]
     */
    public function generateAnnotations(
        EventInterface $event,
        array $diff,
        array $lineCoverage,
        DateTimeImmutable $validUntil
    ): array {
        $annotations = [
            ...$this->annotatePartialBranches($event, $lineCoverage, $validUntil),
            ...$this->annotateBlocksOfMissingCoverage($event, $diff, $lineCoverage, $validUntil),
        ];

        return $annotations;
    }

    /**
     * Find all of the partial branches in the line coverage, and annotate them
     * using context from the diff.
     *
     * @param LineCoverageQueryResult[] $lineCoverage
     *
     * @return PublishableAnnotationInterface[]
     */
    private function annotatePartialBranches(
        EventInterface $event,
        array $lineCoverage,
        DateTimeImmutable $validUntil
    ): array {
        $annotations = [];

        foreach ($lineCoverage as $line) {
            if (
                !in_array(LineType::BRANCH, $line->getTypes()) ||
                $line->getState() === LineState::COVERED
            ) {
                continue;
            }

            $annotations[] = new PublishablePartialBranchAnnotationMessage(
                $event,
                $line->getFileName(),
                $line->getLineNumber(),
                $line->getLineNumber(),
                $line->getTotalBranches(),
                $line->getCoveredBranches(),
                $validUntil
            );
        }

        return $annotations;
    }

    /**
     * Annotate sequential blocks of missing coverage, including those originating from
     * method definitions.
     *
     * @param int[][] $diff
     * @param LineCoverageQueryResult[] $lineCoverage
     *
     * @return PublishableAnnotationInterface[]
     */
    public function annotateBlocksOfMissingCoverage(
        EventInterface $event,
        array $diff,
        array $lineCoverage,
        DateTimeImmutable $validUntil
    ): array {
        $annotations = [];

        /**
         * @var array<string, array<int, LineCoverageQueryResult>> $indexedCoverage
         */
        $indexedCoverage = array_reduce(
            $lineCoverage,
            function (array $index, LineCoverageQueryResult $line) {
                $index[$line->getFileName()][$line->getLineNumber()] = $line;

                return $index;
            },
            []
        );

        foreach ($diff as $fileName => $lineNumbers) {
            $startLine = null;
            $previousLineNumber = null;

            foreach ($lineNumbers as $lineNumber) {
                $lineCoverage = $indexedCoverage[$fileName][$lineNumber] ?? null;

                if ($lineCoverage === null) {
                    continue;
                }

                $isBranch = in_array(LineType::BRANCH, $lineCoverage->getTypes());
                $isNewMethod = in_array(LineType::METHOD, $lineCoverage->getTypes());
                $isLineCovered = $lineCoverage->getState() === LineState::COVERED;
                $isSplitUpInDiff = $previousLineNumber &&
                    $lineNumber - $previousLineNumber > 1;

                if (
                    $startLine &&
                    (
                        $isNewMethod ||
                        $isLineCovered ||
                        $isSplitUpInDiff
                    )
                ) {
                    $isPlacedOnMethod = in_array(LineType::METHOD, $startLine->getTypes());

                    if (
                        !$isPlacedOnMethod ||
                        $startLine->getLineNumber() !== $previousLineNumber
                    ) {
                        // Publishing an annotation when a method signatures change isn't massively helpful,
                        // so we only want to push method-based annotations when theres more than just
                        // one line changed.
                        $annotations[] = new PublishableMissingCoverageAnnotationMessage(
                            $event,
                            $fileName,
                            $isPlacedOnMethod,
                            $startLine->getLineNumber(),
                            $previousLineNumber,
                            $validUntil
                        );
                    }

                    $startLine = !$isLineCovered ? $lineCoverage : null;
                    $previousLineNumber = null;

                    continue;
                }

                if (
                    $startLine === null &&
                    !$isBranch &&
                    !$isLineCovered
                ) {
                    // Only start a new block of missing coverage if we're not on a
                    // branch (as there'll be a partial branch annotation for that),
                    // and if the line is not covered.
                    $startLine = $lineCoverage;
                }

                if ($startLine !== null) {
                    $previousLineNumber = $lineCoverage->getLineNumber();
                }
            }

            if ($startLine && $previousLineNumber) {
                $annotations[] = new PublishableMissingCoverageAnnotationMessage(
                    $event,
                    $fileName,
                    in_array(LineType::METHOD, $startLine->getTypes()),
                    $startLine->getLineNumber(),
                    $previousLineNumber,
                    $validUntil
                );
            }
        }

        return $annotations;
    }
}
