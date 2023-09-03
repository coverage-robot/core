<?php
// phpcs:ignoreFile

namespace App\Service\Formatter;

use Packages\Models\Model\PublishableMessage\PublishablePullRequestMessage;
use Packages\Models\Model\Upload;

class PullRequestCommentFormatterService
{
    public function format(Upload $upload, PublishablePullRequestMessage $message): string
    {
        return <<<MARKDOWN
        ## Coverage Report
        > Merging #{$upload->getPullRequest()} which has **{$message->getSuccessfulUploads(
        )}** successfully uploaded coverage files (and {$message->getPendingUploads(
        )} still pending) on {$upload->getCommit()}

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

        *Last update to `{$upload->getTag()->getName()}` at {$upload->getIngestTime()->format('H:i T')}*
        MARKDOWN;
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
