<?php
// phpcs:ignoreFile

namespace App\Service\Formatter;

use DateTimeZone;
use Packages\Models\Model\PublishableMessage\PublishablePullRequestMessage;
use Packages\Models\Model\Upload;

class PullRequestCommentFormatterService
{
    public function format(Upload $upload, PublishablePullRequestMessage $message): string
    {
        return <<<MARKDOWN
        ## Coverage Report
        {$this->getSummary($upload, $message)}

        | Total Coverage | Diff Coverage |
        | --- | --- |
        | {$message->getCoveragePercentage()}% | {$message->getDiffCoveragePercentage()}% |

        <details>
          <summary>Tags</summary>

          {$this->getTagCoverageTable($upload, $message)}
        </details>

        <details>
          <summary>Impacted Files</summary>

          {$this->getFileImpactTable($upload, $message)}
        </details>

        *Last update to `{$upload->getTag()->getName()}` at {$this->getLastUpdateTime($upload)}*
        MARKDOWN;
    }

    private function getSummary(Upload $upload, PublishablePullRequestMessage $message): string
    {
        $pendingUploads = $message->getPendingUploads() > 0 ? sprintf(
            ' (and **%s** still pending)',
            $message->getPendingUploads()
        ) : '';

        return "> Merging #{$upload->getPullRequest()} which has **{$message->getSuccessfulUploads(
        )}** successfully uploaded coverage file(s){$pendingUploads} on {$upload->getCommit()}";
    }

    private function getLastUpdateTime(Upload $upload): string
    {
        return $upload->getIngestTime()
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('H:ia e');
    }

    private function getTagCoverageTable(Upload $upload, PublishablePullRequestMessage $message): string
    {
        if (count($message->getTagCoverage()) == 0) {
            return "> No uploaded tags in #{$upload->getPullRequest()}";
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
                            $tag["tag"]["commit"] != $upload->getCommit() ?
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

    private function getFileImpactTable(Upload $upload, PublishablePullRequestMessage $message): string
    {
        $files = $message->getLeastCoveredDiffFiles();

        if (count($files) == 0) {
            return "> No impacted files in #{$upload->getPullRequest()}";
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
