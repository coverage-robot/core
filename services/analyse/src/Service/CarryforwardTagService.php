<?php

namespace App\Service;

use App\Enum\QueryParameter;
use App\Model\QueryParameterBag;
use App\Query\CommitTagsHistoryQuery;
use App\Query\Result\MultiCommitQueryResult;
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

    public function getTagsToCarryforward(Upload $upload): array
    {
//        $upload = Upload::from([
//            'commit' => "652d546ba2e0f1a8642e8dc6848b60b14fdc2c9d",
//            'repository' => "portfolio",
//            'provider' => Provider::GITHUB->value,
//            'owner' => "ryanmab",
//            'ref' => "dependabot/composer/services/backend/phpunit/phpunit-10.2.5",
//            'uploadid' => '',
//            'parent' => [],
//            'tag' => ''
//        ]);

        $commits = $this->commitHistoryService->getPrecedingCommits($upload);

        $params = QueryParameterBag::fromUpload($upload);
        $params->set(QueryParameter::COMMIT, [
            $params->get(QueryParameter::COMMIT),
            ...$commits
        ]);

        /**
         * @var MultiCommitQueryResult $commitsAndTags
         */
        $commitsAndTags = $this->queryService->runQuery(
            CommitTagsHistoryQuery::class,
            $params
        );

        $carryforwardTags = [];
        $tagsRecorded = [];

        foreach ($commitsAndTags->getCommits() as $commitAndTag) {
            $tagsNotSeen = array_diff($commitAndTag->getTags(), $tagsRecorded);

            if ($tagsNotSeen === []) {
                continue;
            }

            $carryforwardTags[$commitAndTag->getCommit()] = [
                ...($carryforwardTags[$commitAndTag->getCommit()] ?? []),
                ...$tagsNotSeen
            ];

            $tagsRecorded = array_merge($tagsRecorded, $tagsNotSeen);
        }

        $this->carryforwardLogger->info(
            sprintf(
                "%s commits being used to carryfoward unsubmitted tags for %s",
                count($carryforwardTags),
                (string)$upload
            ),
            [
                'upload' => $upload,
                'carryforwardTags' => $carryforwardTags
            ]
        );

        return $carryforwardTags;
    }
}
