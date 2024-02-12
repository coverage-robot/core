<?php

namespace App\Extension\Function;

use Override;
use Packages\Contracts\PublishableMessage\PublishableMessage;
use Packages\Message\PublishableMessage\PublishableMissingCoverageAnnotationMessage;
use Packages\Message\PublishableMessage\PublishablePartialBranchAnnotationMessage;

final class AnnotationFunction implements TwigFunctionInterface
{
    use ContextAwareFunctionTrait;

    public function call(array $context): array
    {
        $annotation = $this->getAnnotationFromContext($context);

        return [
            'type' => match ($annotation->getType()) {
                PublishableMessage::MISSING_COVERAGE_ANNOTATION => 'missing_coverage',
                PublishableMessage::PARTIAL_BRANCH_ANNOTATION => 'partial_branch'
            },
            'fileName' => $annotation->getFileName(),
            'startLineNumber' => $annotation->getStartLineNumber(),
            'endLineNumber' => $annotation->getEndLineNumber(),
            'coveredBranches' => $annotation instanceof PublishablePartialBranchAnnotationMessage
                ? $annotation->getCoveredBranches()
                : null,
            'totalBranches' => $annotation instanceof PublishablePartialBranchAnnotationMessage
                ? $annotation->getTotalBranches()
                : null,
            'isStartingOnMethod' => $annotation instanceof PublishableMissingCoverageAnnotationMessage
                ? $annotation->isStartingOnMethod()
                : null,
        ];
    }

    #[Override]
    public static function getFunctionName(): string
    {
        return 'annotation';
    }
}
