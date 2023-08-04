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
use Packages\Models\Model\Upload;
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
     * @throws QueryException
     */
    public function getTagsToCarryforward(Upload $upload): array
    {
        $uploadedTags = $this->getCurrentTags($upload);
        $carryableCommitTags = $this->getParentCommitTags($upload);

        $carryforwardTags = [];

        foreach ($carryableCommitTags as $tags) {
            /** @var Tag[] $tagsNotSeen */
            $tagsNotSeen = array_udiff(
                $tags,
                [...$uploadedTags, ...$carryforwardTags],
                static fn(Tag $a, Tag $b) => $a->getName() <=> $b->getName()
            );

            if ($tagsNotSeen === []) {
                continue;
            }

            $carryforwardTags += [...$carryforwardTags, ...$tagsNotSeen];
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
     * @throws QueryException
     */
    private function getCurrentTags(Upload $upload): array
    {
        /**
         * @var CommitCollectionQueryResult $tags
         */
        $tags = $this->queryService->runQuery(CommitTagsQuery::class, QueryParameterBag::fromUpload($upload));

        return $tags->getCommits()[0]->getTags();
    }

    /**
     * @return Tag[][]
     * @throws QueryException|GoogleException
     */
    private function getParentCommitTags(Upload $upload): array
    {
        $commitHistory = $this->commitHistoryService->getPrecedingCommits($upload);

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

    private function mapTagsToCommitHistory(array $commitHistory, CommitCollectionQueryResult $uploadedCommits): array
    {
        $history = [];

        foreach ($commitHistory as $commit) {
            $history[] = array_reduce(
                $uploadedCommits->getCommits(),
                static fn (array $tags, CommitQueryResult $result) => $result->getCommit() === $commit ?
                    [...$tags, ...$result->getTags()] :
                    $tags,
                []
            );
        }

        return $history;
    }
}
