<?php

namespace App\Extension\Function;

use Override;
use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use Packages\Message\PublishableMessage\PublishableCheckRunMessage;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;

final class MetricsFunction implements TwigFunctionInterface
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
                    static fn(array $tag) => [
                        'name' => (string)$tag["tag"]["name"],
                        'commit' => (string)$tag["tag"]["commit"],
                        'lines' => (int)$tag["lines"],
                        'covered' => (int)$tag["covered"],
                        'partial' => (int)$tag["partial"],
                        'uncovered' => (int)$tag["uncovered"],
                        'coveragePercentage' => (float)$tag["coveragePercentage"],
                    ],
                    $message->getTagCoverage()
                ),
                'impacted_files' => array_map(
                    static fn(array $file) => [
                        'fileName' => (string)$file["fileName"],
                        'coveragePercentage' => (float)$file["coveragePercentage"],
                    ],
                    $message->getLeastCoveredDiffFiles()
                )
            ],
            $message instanceof PublishableCheckRunMessage => [
                'total_coverage' => $message->getCoveragePercentage(),
                'coverage_change' => $message->getCoverageChange(),
            ],
            default => []
        };
    }

    #[Override]
    public static function getFunctionName(): string
    {
        return 'metrics';
    }
}
