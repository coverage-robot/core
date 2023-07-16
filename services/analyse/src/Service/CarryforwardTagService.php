<?php

namespace App\Service;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\CommitTagsHistoryQuery;
use App\Query\Result\MultiCommitQueryResult;
use Packages\Models\Model\Tag;
use Packages\Models\Model\Upload;
use Psr\Log\LoggerInterface;

class CarryforwardTagService
{
    public function __construct(
        private readonly CommitHistoryService $commitHistoryService,
        private readonly QueryService $queryService,
        private readonly LoggerInterface $carryforwardLogger
    ) {
    }

    /**
     * @return Tag[]
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
         * @var MultiCommitQueryResult $commitsAndTags
         */
        $commitsAndTags = $this->queryService->runQuery(
            CommitTagsHistoryQuery::class,
            $params
        );

        $carryforwardTags = [];
        $tagsRecorded = [$upload->getTag()->getName()];

        foreach ($commitsAndTags->getCommits() as $commitAndTag) {
            $tagsNotSeen = array_diff($commitAndTag->getTags(), $tagsRecorded);

            if ($tagsNotSeen === []) {
                continue;
            }

            $carryforwardTags[$commitAndTag->getCommit()] = [
                ...($carryforwardTags[$commitAndTag->getCommit()] ?? []),
                ...array_map(static fn(string $tag) => new Tag($tag, $commitAndTag->getCommit()), $tagsNotSeen)
            ];

            $tagsRecorded = array_merge($tagsRecorded, $tagsNotSeen);
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

        return $carryforwardTags;
    }
}
