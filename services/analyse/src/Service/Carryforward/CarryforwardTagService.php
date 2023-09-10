<?php

namespace App\Service\Carryforward;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\CommitTagsQuery;
use App\Query\Result\CommitCollectionQueryResult;
use App\Query\Result\CommitQueryResult;
use App\Service\History\CommitHistoryService;
use App\Service\QueryService;
use Google\Cloud\Core\Exception\GoogleException;
use Packages\Models\Model\Tag;
use Packages\Models\Model\Event\Upload;
use Psr\Log\LoggerInterface;

class CarryforwardTagService implements CarryforwardTagServiceInterface
{
    public function __construct(
        private readonly CommitHistoryService $commitHistoryService,
        private readonly QueryService $queryService,
        private readonly LoggerInterface $carryforwardLogger
    ) {
    }

    /**
     * @throws QueryException|GoogleException
     */
    public function getTagsToCarryforward(Upload $upload): array
    {
        $uploadedTags = $this->getCurrentTags($upload);
        $carryableCommitTags = $this->getParentCommitTags($upload);

        $carryforwardTags = [];

        foreach ($carryableCommitTags as $tags) {
            $tagsNotSeen = array_udiff(
                $tags,
                [...$uploadedTags, ...$carryforwardTags],
                static fn(Tag $a, Tag $b) => $a->getName() <=> $b->getName()
            );

            if ($tagsNotSeen === []) {
                continue;
            }

            /** @var Tag[] $carryforwardTags */
            $carryforwardTags = [...$carryforwardTags, ...$tagsNotSeen];
        }

        $this->carryforwardLogger->info(
            sprintf(
                '%s tags being carried forward for %s',
                count($carryforwardTags),
                (string)$upload
            ),
            [
                'upload' => $upload,
                'tags' => $carryforwardTags
            ]
        );

        return $carryforwardTags;
    }

    /**
     * Get all of the tags uploaded for a particular upload.
     *
     * @throws QueryException|GoogleException
     */
    private function getCurrentTags(Upload $upload): array
    {
        /**
         * @var CommitCollectionQueryResult $tags
         */
        $tags = $this->queryService->runQuery(CommitTagsQuery::class, QueryParameterBag::fromUpload($upload));

        if (empty($tags->getCommits())) {
            // Generally we shouldn't get there, as its a pretty safe assumption that there
            // should be _at least_ one commit, with one tag (the one we're analysing currently),
            // however, on the off chance something goes wrong, we should just to double check.
            return [];
        }

        return $tags->getCommits()[0]->getTags();
    }

    /**
     * Get the commit history of a particular upload, with the tags that were uploaded at each commit.
     *
     * @return Tag[][]
     * @throws QueryException|GoogleException
     */
    private function getParentCommitTags(Upload $upload): array
    {
        $commitHistory = $this->commitHistoryService->getPrecedingCommits($upload);

        if ($commitHistory === []) {
            // No proceeding commits in the tree, so there will no tags to carry forward.
            return [];
        }

        $precedingUploadedTags = QueryParameterBag::fromUpload($upload);
        $precedingUploadedTags->set(
            QueryParameter::COMMIT,
            $commitHistory
        );

        $results = $this->queryService->runQuery(
            CommitTagsQuery::class,
            $precedingUploadedTags
        );

        if (!$results instanceof CommitCollectionQueryResult) {
            throw new QueryException(
                sprintf(
                    'Received incorrect query result. Expected %s, got %s',
                    CommitCollectionQueryResult::class,
                    get_class($results)
                )
            );
        }

        return $this->mapTagsToCommitHistory($commitHistory, $results);
    }

    /**
     * Map a list of commits (descending order of commit tree) to a list of tagged coverage uploaded
     * historically.
     *
     * This removes non-determinism in the order of commits returned by BigQuery by mapping the data into
     * a deterministic order taken directly from the History services.
     *
     * @param string[] $commitHistory
     * @return Tag[][]
     */
    private function mapTagsToCommitHistory(array $commitHistory, CommitCollectionQueryResult $uploadedCommits): array
    {
        $history = [];

        foreach ($commitHistory as $commit) {
            /** @var Tag[] $commitTags */
            $commitTags = array_reduce(
                $uploadedCommits->getCommits(),
                static fn(array $tags, CommitQueryResult $result) => $result->getCommit() === $commit ?
                    [...$tags, ...$result->getTags()] :
                    $tags,
                []
            );

            $history[] = $commitTags;
        }

        return $history;
    }
}
