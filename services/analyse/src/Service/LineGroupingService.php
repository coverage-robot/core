<?php

namespace App\Service;

use App\Query\Result\LineCoverageQueryResult;
use DateTimeImmutable;
use Packages\Contracts\Line\LineState;
use Packages\Contracts\Line\LineType;
use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use Packages\Event\Model\EventInterface;
use Packages\Message\PublishableMessage\PublishableLineCommentInterface;
use Packages\Message\PublishableMessage\PublishableMissingCoverageLineCommentMessage;
use Packages\Message\PublishableMessage\PublishablePartialBranchLineCommentMessage;
use Psr\Log\LoggerInterface;

final class LineGroupingService
{
    public function __construct(
        private readonly LoggerInterface $lineGroupingLogger
    ) {
    }

    /**
     * Group lines into comments which can be published.
     *
     * This takes into account a number of scenarios:
     * 1. Blocks of missing coverage (i.e. grouping 10 sequential uncovered lines)
     * 2. Missing coverage within method definitions (i.e. grouping 5 sequential
     *    lines within a method which are uncovered)
     * 3. Partial branch coverage (i.e. annotating branches which are not fully covered)
     *
     * @param array<string, array<int, int>> $diff
     * @param LineCoverageQueryResult[] $lineCoverage
     *
     * @return PublishableLineCommentInterface[]
     */
    public function generateComments(
        EventInterface $event,
        array $diff,
        array $lineCoverage,
        DateTimeImmutable $validUntil
    ): array {
        $annotations = [
            ...$this->annotatePartialBranches($event, $lineCoverage, $validUntil),
            ...$this->annotateBlocksOfMissingCoverage($event, $diff, $lineCoverage, $validUntil),
        ];

        $this->lineGroupingLogger->info(
            sprintf(
                'Generated %d comments for %s from %s lines of coverage.',
                count($annotations),
                (string)$event,
                count($lineCoverage)
            ),
            $annotations
        );

        return $annotations;
    }

    /**
     * Find all of the partial branches in the line coverage, and annotate them
     * using context from the diff.
     *
     * @param LineCoverageQueryResult[] $lineCoverage
     *
     * @return PublishableMessageInterface[]
     */
    private function annotatePartialBranches(
        EventInterface $event,
        array $lineCoverage,
        DateTimeImmutable $validUntil
    ): array {
        $annotations = [];

        foreach ($lineCoverage as $line) {
            if (!in_array(LineType::BRANCH, $line->getTypes())) {
                continue;
            }

            if ($line->getState() === LineState::COVERED) {
                continue;
            }

            if ($line->getTotalBranches() === $line->getCoveredBranches()) {
                // This appears to be some kind of error - where we're incorrectly reporting a line as
                // uncovered, when all of the branches seem to have been hit (indicating full coverage)
                $this->lineGroupingLogger->warning(
                    'Found a branch which is reported as not fully covered, but all of the branches are covered.',
                    [
                        'line' => $line,
                        'totalBranches' => $line->getTotalBranches(),
                        'coveredBranches' => $line->getCoveredBranches(),
                        'event' => $event,
                    ]
                );

                continue;
            }

            $annotations[] = new PublishablePartialBranchLineCommentMessage(
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
     * @param array<string, array<int, int>> $diff
     * @param LineCoverageQueryResult[] $line
     *
     * @return PublishableMissingCoverageLineCommentMessage[]
     */
    public function annotateBlocksOfMissingCoverage(
        EventInterface $event,
        array $diff,
        array $line,
        DateTimeImmutable $validUntil
    ): array {
        $annotations = [];

        $indexedCoverage = $this->getIndexedLineCoverage($line);

        foreach ($indexedCoverage as $fileName => $coverage) {
            $missingStartLine = null;
            $missingEndLine = null;

            foreach ($coverage as $line) {
                if (
                    $missingStartLine !== null &&
                    $missingEndLine !== null &&
                    $this->shouldCompleteMissingCoverageBlock(
                        $line,
                        $missingEndLine,
                        $diff[$fileName] ?? []
                    )
                ) {
                    // We've reached the end of a block of missing coverage, so we should complete
                    $annotations[] = $this->generateMissingCoverageAnnotation(
                        $event,
                        $missingStartLine,
                        $missingEndLine,
                        $validUntil
                    );

                    $missingStartLine = null;
                }

                if (
                    $missingStartLine === null &&
                    $this->shouldStartMissingCoverageBlock($line)
                ) {
                    $missingStartLine = $line;
                }

                $missingEndLine = $missingStartLine !== null ? $line : null;
            }

            if ($missingStartLine === null) {
                continue;
            }

            if ($missingEndLine === null) {
                continue;
            }

            $annotations[] = $this->generateMissingCoverageAnnotation(
                $event,
                $missingStartLine,
                $missingEndLine,
                $validUntil
            );
        }

        return $annotations;
    }

    /**
     * Check if a block of missing coverage should be started at a current line.
     *
     * This will happen if:
     * 1. The current line is not covered (i.e. is missing coverage
     * 1. We're not currently on a branch (because that'll have a partial branch annotation)
     */
    private function shouldStartMissingCoverageBlock(LineCoverageQueryResult $currentLine): bool
    {
        $isBranch = in_array(LineType::BRANCH, $currentLine->getTypes());
        $isLineCovered = $currentLine->getState() === LineState::COVERED;

        return !$isBranch && !$isLineCovered;
    }

    /**
     * Check if a block of missing coverage should be completed at a current line.
     *
     * This covers three scenarios:
     * 1. We've reached the end of a method definition (i.e. we've seen a new method signature come up)
     * 2. The current line is now covered (i.e. the block of missing coverage has ended)
     * 3. The current line is not sequential to the previous line (i.e. there's a gap in the diff - leaving
     *    space for existing lines of code, unchanged by the commit)
     *
     * @param array<int, int> $fileDiff
     */
    private function shouldCompleteMissingCoverageBlock(
        LineCoverageQueryResult $currentLine,
        LineCoverageQueryResult $missingEndLine,
        array $fileDiff
    ): bool {
        $missingEndDiffIndex = array_search($missingEndLine->getLineNumber(), $fileDiff, true);
        $currentLineDiffIndex = array_search($currentLine->getLineNumber(), $fileDiff, true);

        $isNewMethod = in_array(LineType::METHOD, $currentLine->getTypes());
        $isLineCovered = $currentLine->getState() === LineState::COVERED;

        // Compare the indexes of the lines in the diff, to the line numbers themselves.
        // If their differences are equal (i.e. theres 10 lines between the two in the
        // diff, and 10 between the coverage line numbers) it means the diff is still
        // sequential (i.e. not broken up by unchanged lines - which wouldn't show in the diff).
        $isDiffSequential = ($missingEndDiffIndex !== false && $currentLineDiffIndex !== false) &&
            ($currentLineDiffIndex - $missingEndDiffIndex) ===
            ($currentLine->getLineNumber() - $missingEndLine->getLineNumber());

        return $isNewMethod ||
            $isLineCovered ||
            !$isDiffSequential;
    }

    /**
     * Generate a missing coverage annotation for a block of missing coverage.
     */
    private function generateMissingCoverageAnnotation(
        EventInterface $event,
        LineCoverageQueryResult $startLine,
        LineCoverageQueryResult $endLine,
        DateTimeImmutable $validUntil
    ): PublishableMissingCoverageLineCommentMessage {
        return new PublishableMissingCoverageLineCommentMessage(
            $event,
            $startLine->getFileName(),
            in_array(LineType::METHOD, $startLine->getTypes()),
            $startLine->getLineNumber(),
            $endLine->getLineNumber(),
            $validUntil
        );
    }

    /**
     * Index the line coverage by file name and line number, so that lookups against the
     * diff are easier.
     *
     * @param LineCoverageQueryResult[] $coverage
     * @return array<string, array<int, LineCoverageQueryResult>>
     */
    private function getIndexedLineCoverage(array $coverage): array
    {
        return array_reduce(
            $coverage,
            static function (array $index, LineCoverageQueryResult $line) {
                if (!isset($index[$line->getFileName()])) {
                    $index[$line->getFileName()] = [];
                }

                /** @var array<string, array<int, LineCoverageQueryResult>> $index */
                $index[$line->getFileName()][$line->getLineNumber()] = $line;

                return $index;
            },
            []
        );
    }
}
