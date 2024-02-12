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

        $properties = [
            'type' => match ($annotation->getType()) {
                PublishableMessage::MISSING_COVERAGE_ANNOTATION => 'missing_coverage',
                PublishableMessage::PARTIAL_BRANCH_ANNOTATION => 'partial_branch',
                default => null,
            },
            'fileName' => $annotation->getFileName(),
            'startLineNumber' => $annotation->getStartLineNumber(),
            'endLineNumber' => $annotation->getEndLineNumber(),
        ];

        switch (true) {
            case $annotation instanceof PublishablePartialBranchAnnotationMessage:
                $properties = array_merge(
                    $properties,
                    [
                        'coveredBranches' => $annotation->getCoveredBranches(),
                        'totalBranches' => $annotation->getTotalBranches(),
                    ]
                );
                break;
            case $annotation instanceof PublishableMissingCoverageAnnotationMessage:
                $properties = array_merge(
                    $properties,
                    [
                        'isStartingOnMethod' => $annotation->isStartingOnMethod(),
                    ]
                );
                break;
        }

        return $properties;
    }

    #[Override]
    public static function getFunctionName(): string
    {
        return 'annotation';
    }
}
