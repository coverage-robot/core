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
use Psr\Log\LoggerInterface;

class LineGroupingService
{
    public function __construct(
        private readonly LoggerInterface $lineGroupingLogger
    ) {
    }

    /**
     * Group lines into annotations which can be published.
     *
     * This takes into account a number of scenarios:
     * 1. Blocks of missing coverage (i.e. grouping 10 sequential uncovered lines)
     * 2. Missing coverage within method definitions (i.e. grouping 5 sequential
     *    lines within a method which are uncovered)
     * 3. Partial branch coverage (i.e. annotating branches which are not fully covered)
     *
     * @param array<string, int[]> $diff
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

        $this->lineGroupingLogger->info(
            sprintf(
                'Generated %d annotations for %s from %s lines of coverage.',
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
     * @param array<string, int[]> $diff
     * @param LineCoverageQueryResult[] $line
     *
     * @return PublishableAnnotationInterface[]
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
            $isBlockStarted = false;

            foreach ($coverage as $line) {
                if (
                    $isBlockStarted &&
                    $this->shouldCompleteMissingCoverageBlock($line, $diff[$fileName] ?? [])
                ) {
                    // We've reached the end of a block of missing coverage, so we should complete
                    $annotations[] = $this->generateMissingCoverageAnnotation(
                        $event,
                        $missingStartLine,
                        $missingEndLine,
                        $validUntil
                    );

                    $isBlockStarted = false;
                }

                if (
                    !$isBlockStarted &&
                    $this->shouldStartMissingCoverageBlock($line)
                ) {
                    $isBlockStarted = true;
                    $missingStartLine = $line;
                }

                $missingEndLine = $isBlockStarted ? $line : null;
            }

            if ($isBlockStarted) {
                $annotations[] = $this->generateMissingCoverageAnnotation(
                    $event,
                    $missingStartLine,
                    $missingEndLine,
                    $validUntil
                );
            }
        }

        return array_filter($annotations);
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
     */
    private function shouldCompleteMissingCoverageBlock(LineCoverageQueryResult $currentLine, array $fileDiff): bool
    {
        $isNewMethod = in_array(LineType::METHOD, $currentLine->getTypes());
        $isLineCovered = $currentLine->getState() === LineState::COVERED;
        $isDiffSequential = in_array(
            $currentLine->getLineNumber() - 1,
            $fileDiff
        );

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
    ): ?PublishableAnnotationInterface {
        return new PublishableMissingCoverageAnnotationMessage(
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
