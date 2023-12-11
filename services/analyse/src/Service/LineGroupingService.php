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
     * @param LineCoverageQueryResult[] $lines
     *
     * @return PublishableAnnotationInterface[]
     */
    public function generateAnnotations(
        EventInterface $event,
        array $lines,
        DateTimeImmutable $validUntil
    ): array {
        $annotations = [];

        $startLine = null;
        $endLine = null;

        foreach ($lines as $line) {
            if (
                in_array(LineType::BRANCH, $line->getTypes()) &&
                $line->getState() === LineState::PARTIAL
            ) {
                $annotations[] = new PublishablePartialBranchAnnotationMessage(
                    $event,
                    $line->getFileName(),
                    $line->getLineNumber(),
                    $line->getTotalBranches(),
                    $line->getCoveredBranches(),
                    $validUntil
                );
            }

            if (
                $startLine &&
                (
                    $line->getState() === LineState::COVERED ||
                    $line->getFileName() !== $startLine->getFileName() ||
                    in_array(LineType::METHOD, $line->getTypes())
                )
            ) {
                $annotations[] = new PublishableMissingCoverageAnnotationMessage(
                    $event,
                    $startLine->getFileName(),
                    in_array(LineType::METHOD, $startLine->getTypes()),
                    $startLine->getLineNumber(),
                    $endLine?->getLineNumber() ?? $startLine->getLineNumber(),
                    $validUntil
                );
                $startLine = $line->getState() !== LineState::COVERED ? $line : null;
                $endLine = null;

                continue;
            }

            if (
                !$startLine &&
                $line->getState() !== LineState::COVERED
            ) {
                $startLine = $line;
            }

            $endLine = $line;
        }

        if ($startLine) {
            $annotations[] = new PublishableMissingCoverageAnnotationMessage(
                $event,
                $startLine->getFileName(),
                in_array(LineType::METHOD, $startLine->getTypes()),
                $startLine->getLineNumber(),
                $endLine?->getLineNumber() ?? $startLine->getLineNumber(),
                $validUntil
            );
        }

        return $annotations;
    }
}
