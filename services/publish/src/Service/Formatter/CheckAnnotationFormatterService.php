<?php

namespace App\Service\Formatter;

use Packages\Message\PublishableMessage\PublishableAnnotationInterface;
use Packages\Message\PublishableMessage\PublishableMissingCoverageAnnotationMessage;
use Packages\Message\PublishableMessage\PublishablePartialBranchAnnotationMessage;

class CheckAnnotationFormatterService
{
    public function formatTitle(): string
    {
        return 'Opportunity For New Coverage';
    }

    public function format(
        PublishableAnnotationInterface $annotation
    ): string {
        return match ($annotation::class) {
            PublishableMissingCoverageAnnotationMessage::class => $this->formatMissingCoverageAnnotation($annotation),
            PublishablePartialBranchAnnotationMessage::class => $this->formatPartialBranchAnnotation($annotation),
        };
    }

    private function formatMissingCoverageAnnotation(
        PublishableMissingCoverageAnnotationMessage $annotationMessage
    ): string {
        $totalMissingLines = $annotationMessage->getEndLineNumber() - $annotationMessage->getStartLineNumber();

        if ($annotationMessage->isStartingOnMethod()) {
            return match ($totalMissingLines) {
                0, 1 => 'This method has not been covered by any tests.',
                default => sprintf(
                    'The next %d lines of this method are not covered by any tests.',
                    $totalMissingLines
                )
            };
        }

        return match ($totalMissingLines) {
            0 => 'This line is not covered by any tests.',
            default => sprintf(
                'The next %d lines are not covered by any tests.',
                $totalMissingLines
            )
        };
    }

    private function formatPartialBranchAnnotation(
        PublishablePartialBranchAnnotationMessage $annotationMessage
    ): string {
        $coveredBranches = round(
            ($annotationMessage->getCoveredBranches() / $annotationMessage->getTotalBranches())
            * 100
        );

        return match ($coveredBranches) {
            0.0 => 'None of these branches are covered by tests.',
            default => sprintf(
                '%s%% of these branches are not covered by any tests.',
                100 - $coveredBranches
            ),
        };
    }
}
