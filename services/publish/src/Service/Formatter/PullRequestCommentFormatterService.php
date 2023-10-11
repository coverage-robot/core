<?php
// phpcs:ignoreFile

namespace App\Service\Formatter;

use DateTimeZone;
use Packages\Models\Model\Event\EventInterface;
use Packages\Models\Model\PublishableMessage\PublishablePullRequestMessage;

class PullRequestCommentFormatterService
{
    public function format(EventInterface $event, PublishablePullRequestMessage $message): string
    {
        return <<<MARKDOWN
        ## Coverage Report
        {$this->getSummary($event, $message)}

        | Total Coverage | Diff Coverage |
        | --- | --- |
        | {$message->getCoveragePercentage()}% | {$message->getDiffCoveragePercentage()}% |

        <details>
          <summary>Tags</summary>

          {$this->getTagCoverageTable($event, $message)}
        </details>

        <details>
          <summary>Impacted Files</summary>

          {$this->getFileImpactTable($event, $message)}
        </details>

        *Last update to `{$event->getTag()->getName()}` at {$this->getLastUpdateTime($event)}*
        MARKDOWN;
    }

    private function getSummary(EventInterface $event, PublishablePullRequestMessage $message): string
    {
        $pendingUploads = $message->getPendingUploads() > 0 ? sprintf(
            ' (and **%s** still pending)',
            $message->getPendingUploads()
        ) : '';

        return "> Merging #{$event->getPullRequest()} which has **{$message->getSuccessfulUploads(
        )}** successfully uploaded coverage file(s){$pendingUploads} on {$event->getCommit()}";
    }

    private function getLastUpdateTime(EventInterface $event): string
    {
        return $event->getEventTime()
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('H:ia e');
    }

    private function getTagCoverageTable(EventInterface $event, PublishablePullRequestMessage $message): string
    {
        if (count($message->getTagCoverage()) == 0) {
            return "> No uploaded tags in #{$event->getPullRequest()}";
        }

        return sprintf(
            <<<MARKDOWN
            | Tag | Lines | Covered | Partial | Uncovered | Coverage |
            | --- | --- | --- | --- | --- | --- |
            %s
            MARKDOWN,
            implode(
                PHP_EOL,
                array_map(
                    static fn(array $tag) => sprintf(
                        '| %s | %s | %s | %s | %s | %s%% |',
                        sprintf(
                            '%s%s',
                            is_array($tag["tag"]) ?
                                (string)$tag["tag"]["name"] :
                                "No name",
                            is_array($tag["tag"]) &&
                            $tag["tag"]["commit"] != $event->getCommit() ?
                                sprintf('<br><sub>(Carried forward from %s)</sub>', (string)$tag["tag"]["commit"]) :
                                ''
                        ),
                        (int)$tag["lines"],
                        (int)$tag["covered"],
                        (int)$tag["partial"],
                        (int)$tag["uncovered"],
                        (float)$tag["coveragePercentage"]
                    ),
                    $message->getTagCoverage()
                )
            )
        );
    }

    private function getFileImpactTable(EventInterface $event, PublishablePullRequestMessage $message): string
    {
        $files = $message->getLeastCoveredDiffFiles();

        if (count($files) == 0) {
            return "> No impacted files in #{$event->getPullRequest()}";
        }

        return sprintf(
            <<<MARKDOWN
            | File | Diff Coverage |
            | --- | --- |
            %s
            MARKDOWN,
            implode(
                PHP_EOL,
                array_map(
                    static fn(array $file) => sprintf(
                        '| %s | %s%% |',
                        (string)$file["fileName"],
                        (float)$file["coveragePercentage"],
                    ),
                    $files
                )
            )
        );
    }
}
