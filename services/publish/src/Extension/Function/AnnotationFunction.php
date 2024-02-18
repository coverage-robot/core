<?php

namespace App\Extension\Function;

use Override;
use Packages\Contracts\PublishableMessage\PublishableMessage;
use Packages\Message\PublishableMessage\PublishableMissingCoverageLineCommentMessage;
use Packages\Message\PublishableMessage\PublishablePartialBranchLineCommentMessage;

final class AnnotationFunction implements TwigFunctionInterface
{
    use ContextAwareFunctionTrait;

    public function call(array $context): array
    {
        $message = $this->getMessageFromContext($context);

        $properties = [
            'type' => match ($message->getType()) {
                PublishableMessage::MISSING_COVERAGE_LINE_COMMENT => 'missing_coverage',
                PublishableMessage::PARTIAL_BRANCH_LINE_COMMENT => 'partial_branch',
                default => null,
            },
            'fileName' => $message->getFileName(),
            'startLineNumber' => $message->getStartLineNumber(),
            'endLineNumber' => $message->getEndLineNumber(),
        ];

        switch (true) {
            case $message instanceof PublishablePartialBranchLineCommentMessage:
                $properties = array_merge(
                    $properties,
                    [
                        'coveredBranches' => $message->getCoveredBranches(),
                        'totalBranches' => $message->getTotalBranches(),
                    ]
                );
                break;
            case $message instanceof PublishableMissingCoverageLineCommentMessage:
                $properties = array_merge(
                    $properties,
                    [
                        'isStartingOnMethod' => $message->isStartingOnMethod(),
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
