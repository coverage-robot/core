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
        $commits = $this->commitHistoryService->getPrecedingCommits($upload);

        $params = QueryParameterBag::fromUpload($upload);
        $params->set(
            QueryParameter::COMMIT,
            [
                $params->get(QueryParameter::COMMIT),
                ...$commits
            ]
        );

        /**
         * @var CommitCollectionQueryResult $commitTags
         */
        $commitTags = $this->queryService->runQuery(
            CommitTagsQuery::class,
            $params
        );

        $carryforwardTags = [];

        /** @var CommitQueryResult $commitAndTag */
        foreach ($commitTags->getCommits() as $commitAndTag) {
            /** @var Tag[] $tagsNotSeen */
            $tagsNotSeen = array_udiff(
                $commitAndTag->getTags(),
                [...$carryforwardTags, $upload->getTag()],
                static fn(Tag $a, Tag $b) => $a->getName() <=> $b->getName()
            );

            if ($tagsNotSeen === []) {
                continue;
            }

            $carryforwardTags += $tagsNotSeen;
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
}
