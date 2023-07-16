<?php

namespace App\Service;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\CommitTagsQuery;
use App\Query\Result\CommitCollectionQueryResult;
use Packages\Models\Model\Tag;
use Packages\Models\Model\Upload;
use Psr\Log\LoggerInterface;
use WeakMap;

class CarryforwardTagService
{
    private WeakMap $cache;

    public function __construct(
        private readonly CommitHistoryService $commitHistoryService,
        private readonly QueryService $queryService,
        private readonly LoggerInterface $carryforwardLogger
    ) {
        $this->cache = new WeakMap();
    }

    /**
     * @return Tag[]
     * @throws QueryException
     */
    public function getTagsToCarryforward(Upload $upload): array
    {
        if (isset($this->cache[$upload])) {
            $this->carryforwardLogger->info(
                sprintf(
                    "Using cached value of %s commits to carryfoward tags for %s",
                    count($this->cache[$upload]),
                    (string)$upload
                ),
                [
                    'upload' => $upload,
                    'tags' => $this->cache[$upload]
                ]
            );

            return $this->cache[$upload];
        }

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
         * @var CommitCollectionQueryResult $commitsAndTags
         */
        $commitsAndTags = $this->queryService->runQuery(
            CommitTagsQuery::class,
            $params
        );

        $carryforwardTags = [];
        $carried = [$upload->getTag()];

        foreach ($commitsAndTags->getCommits() as $commitAndTag) {
            $tagsNotSeen = array_udiff(
                $commitAndTag->getTags(),
                $carried,
                static fn(Tag $a, Tag $b) => $a->getName() <=> $b->getName()
            );

            if ($tagsNotSeen === []) {
                continue;
            }

            $carryforwardTags[$commitAndTag->getCommit()] = [
                ...($carryforwardTags[$commitAndTag->getCommit()] ?? []),
                ...$tagsNotSeen
            ];

            $carried = [
                ...$carried,
                ...$tagsNotSeen
            ];
        }

        $this->carryforwardLogger->info(
            sprintf(
                "%s commits being used to carryfoward tags for %s",
                count($carryforwardTags),
                (string)$upload
            ),
            [
                'upload' => $upload,
                'tags' => $carryforwardTags
            ]
        );

        $this->cache[$upload] = $carryforwardTags;

        return $this->cache[$upload];
    }
}
