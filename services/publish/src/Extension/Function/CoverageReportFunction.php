<?php

namespace App\Extension\Function;

use Override;
use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use Packages\Message\PublishableMessage\PublishableCheckRunMessage;
use Packages\Message\PublishableMessage\PublishableMissingCoverageLineCommentMessage;
use Packages\Message\PublishableMessage\PublishablePartialBranchLineCommentMessage;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;

final class CoverageReportFunction implements TwigFunctionInterface
{
    use ContextAwareFunctionTrait;

    public function call(array $context): array
    {
        return $this->getMetricsForSpecificMessage($this->getMessageFromContext($context));
    }

    /**
     * Get a custom set of metrics based on the message the template is being
     * rendered for.
     */
    private function getMetricsForSpecificMessage(
        PublishableMessageInterface $message
    ): array {
        return match (true) {
            $message instanceof PublishablePullRequestMessage => [
                'total_uploads' => $message->getSuccessfulUploads(),
                'total_coverage' => $message->getCoveragePercentage(),
                'diff_coverage' => $message->getDiffCoveragePercentage(),
                'coverage_change' => $message->getCoverageChange(),
                'tag_coverage' => array_map(
                    static fn(array $tag): array => [
                        'name' => isset($tag['tag']['name']) ? (string)$tag['tag']['name'] : 'unknown',
                        'commit' => isset($tag['tag']['commit']) ? (string)$tag['tag']['commit'] : 'unknown',
                        'lines' => (int)$tag['lines'],
                        'covered' => (int)$tag['covered'],
                        'partial' => (int)$tag['partial'],
                        'uncovered' => (int)$tag['uncovered'],
                        'coverage_percentage' => (float)$tag['coveragePercentage'],
                    ],
                    $message->getTagCoverage()
                ),
                'impacted_files' => array_map(
                    static fn(array $file): array => [
                        'file_name' => (string)$file['fileName'],
                        'coverage_percentage' => (float)$file['coveragePercentage'],
                    ],
                    $message->getLeastCoveredDiffFiles()
                )
            ],
            $message instanceof PublishableCheckRunMessage => [
                'total_coverage' => $message->getCoveragePercentage(),
                'coverage_change' => $message->getCoverageChange(),
            ],
            $message instanceof PublishablePartialBranchLineCommentMessage => [
                'type' => 'partial_branch',
                'fileName' => $message->getFileName(),
                'start' => $message->getStartLineNumber(),
                'end' => $message->getEndLineNumber(),
                'covered_branches' => $message->getCoveredBranches(),
                'total_branches' => $message->getTotalBranches(),
            ],
            $message instanceof PublishableMissingCoverageLineCommentMessage => [
                'type' => 'missing_coverage',
                'fileName' => $message->getFileName(),
                'start' => $message->getStartLineNumber(),
                'end' => $message->getEndLineNumber(),
                'starting_on_method' => $message->isStartingOnMethod(),
            ],
            default => []
        };
    }

    #[Override]
    public static function getFunctionName(): string
    {
        return 'report';
    }
}
