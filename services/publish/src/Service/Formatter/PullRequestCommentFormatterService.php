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
        > Merging #{$upload->getPullRequest()}, with **{$message->getTotalUploads(
        )}** uploaded coverage files on {$upload->getCommit()}

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
                "\n",
                array_map(
                    static fn(array $tag) => sprintf(
                        '| %s | %s | %s | %s | %s | %s%% |',
                        sprintf(
                            '%s%s',
                            $tag["tag"]["name"],
                            $tag["tag"]["commit"] != $upload->getCommit() ? sprintf(
                                '<br><sub>(Carried forward from %s)</sub>',
                                $tag["tag"]["commit"]
                            ) : ''
                        ),
                        $tag["lines"],
                        $tag["covered"],
                        $tag["partial"],
                        $tag["uncovered"],
                        $tag["coveragePercentage"]
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
            <td colspan=3>
            MARKDOWN,
            implode(
                "\n",
                array_map(
                    static fn(array $file) => sprintf(
                        '| %s | %s%% |',
                        $file["fileName"],
                        $file["coveragePercentage"],
                    ),
                    $files
                )
            )
        );
    }
}
