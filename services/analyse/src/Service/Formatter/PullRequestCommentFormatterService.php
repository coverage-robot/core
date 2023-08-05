<?php
// phpcs:ignoreFile

namespace App\Service\Formatter;

use App\Model\PublishableCoverageDataInterface;
use App\Query\Result\FileCoverageQueryResult;
use App\Query\Result\TagCoverageQueryResult;
use Packages\Models\Model\Upload;

class PullRequestCommentFormatterService
{
    public const MAX_IMPACTED_FILES = 10;

    public function format(Upload $upload, PublishableCoverageDataInterface $data): string
    {
        return <<<MARKDOWN
        ## Coverage Report
        > Merging #{$upload->getPullRequest()}, with **{$data->getTotalUploads()}** uploaded coverage files on {$upload->getCommit()}

        | Total Coverage | Diff Coverage |
        | --- | --- |
        | {$data->getCoveragePercentage()}% | {$data->getDiffCoveragePercentage()}% |

        <details>
          <summary>Tags</summary>

          {$this->getTagCoverageTable($upload, $data)}
        </details>

        <details>
          <summary>Impacted Files</summary>

          {$this->getFileImpactTable($upload, $data)}
        </details>

        *Last update to `{$upload->getTag()->getName()}` at {$upload->getIngestTime()->format('H:i e')}*
        MARKDOWN;
    }

    private function getTagCoverageTable(Upload $upload, PublishableCoverageDataInterface $data): string
    {
        if (count($data->getTagCoverage()->getTags()) == 0) {
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
                    static fn (TagCoverageQueryResult $tag) => sprintf(
                        '| %s | %s | %s | %s | %s | %s%% |',
                        sprintf(
                            '%s%s',
                            $tag->getTag()->getName(),
                            $tag->getTag()->getCommit() != $upload->getCommit() ? sprintf('<br><sub>(Carried forward from %s)</sub>', $tag->getTag()->getCommit()) : ''
                        ),
                        $tag->getLines(),
                        $tag->getCovered(),
                        $tag->getPartial(),
                        $tag->getUncovered(),
                        $tag->getCoveragePercentage()
                    ),
                    $data->getTagCoverage()
                        ->getTags()
                )
            )
        );
    }

    private function getFileImpactTable(Upload $upload, PublishableCoverageDataInterface $data): string
    {
        $files = $data->getLeastCoveredDiffFiles(self::MAX_IMPACTED_FILES)->getFiles();

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
                    static fn (FileCoverageQueryResult $tag) => sprintf(
                        '| %s | %s%% |',
                        $tag->getFileName(),
                        $tag->getCoveragePercentage(),
                    ),
                    $files
                )
            )
        );
    }
}
