<?php
// phpcs:ignoreFile

namespace App\Service\Formatter;

use App\Model\PublishableCoverageDataInterface;
use App\Model\QueryResult\TagCoverageQueryResult;
use App\Model\Upload;

class PullRequestCommentFormatterService
{
    public function format(Upload $upload, PublishableCoverageDataInterface $data): string
    {
        return <<<MARKDOWN
        ### New Coverage Information
        This is for {$upload->getCommit()} commit. Which has had {$data->getTotalUploads()} uploads. 
        
        Total coverage is: **{$data->getCoveragePercentage()}%**
        
        Consisting of *{$data->getAtLeastPartiallyCoveredLines()}* covered lines, out of *{$data->getTotalLines()}* total lines.
        
        {$this->getTagCoverageTable($data)}
        MARKDOWN;
    }

    private function getTagCoverageTable(PublishableCoverageDataInterface $data): string
    {
        return sprintf(
            <<<MARKDOWN
            | Tag | Lines | Covered | Partial | Uncovered | Coverage |
            | --- | --- | --- | --- | --- | --- |
            %s
            MARKDOWN,
            implode(
                "\n",
                array_map(
                    fn (TagCoverageQueryResult $tag) => sprintf(
                        '| %s | %s | %s | %s | %s | %s%% |',
                        $tag->getTag(),
                        $tag->getLines(),
                        $tag->getCovered(),
                        $tag->getPartial(),
                        $tag->getUncovered(),
                        $tag->getCoveragePercentage()
                    ),
                    $data->getTagCoverage()->getTags()
                )
            )
        );
    }
}
