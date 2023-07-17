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

        /** @var CommitQueryResult $commitAndTag */
        foreach ($carryableCommitTags->getCommits() as $commitAndTag) {
            /** @var Tag[] $tagsNotSeen */
            $tagsNotSeen = array_udiff(
                $commitAndTag->getTags(),
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
        return $this->queryService->runQuery(CommitTagsQuery::class, QueryParameterBag::fromUpload($upload))
            ->getCommits()[0]
            ?->getTags()
            ?? [];
    }

    /**
     * @throws QueryException
     */
    private function getParentCommitTags(Upload $upload): CommitCollectionQueryResult
    {
        $precedingUploadedTags = QueryParameterBag::fromUpload($upload);
        $precedingUploadedTags->set(
            QueryParameter::COMMIT,
            $this->commitHistoryService->getPrecedingCommits($upload)
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

        return $results;
    }
}
