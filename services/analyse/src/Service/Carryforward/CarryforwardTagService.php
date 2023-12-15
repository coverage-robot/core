<?php

namespace App\Service\Carryforward;

use App\Model\QueryParameterBag;
use App\Model\ReportWaypoint;
use App\Query\Result\TagAvailabilityCollectionQueryResult;
use App\Query\TagAvailabilityQuery;
use App\Service\CachingQueryService;
use App\Service\History\CommitHistoryService;
use App\Service\QueryServiceInterface;
use Packages\Contracts\Event\EventInterface;
use Packages\Models\Model\Tag;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CarryforwardTagService implements CarryforwardTagServiceInterface
{
    /**
     * The total number of pages to look back through in order to match the available tags we've seen
     * uploaded in the past, and the most recent commits in the tree.
     *
     * @see CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE
     */
    private const int MAX_COMMIT_HISTORY_PAGES = 5;

    public function __construct(
        private readonly CommitHistoryService $commitHistoryService,
        #[Autowire(service: CachingQueryService::class)]
        private readonly QueryServiceInterface $queryService,
        private readonly LoggerInterface $carryforwardLogger
    ) {
    }

    /**
     * @param Tag[] $existingTags
     * @return Tag[]
     */
    public function getTagsToCarryforward(EventInterface|ReportWaypoint $event, array $existingTags): array
    {
        $carryforwardTags = [];

        /** @var TagAvailabilityCollectionQueryResult $tagAvailability */
        $tagAvailability = $this->queryService->runQuery(
            TagAvailabilityQuery::class,
            QueryParameterBag::fromWaypoint($event)
        );

        /**
         * @var string[] $tagsNotSeen
         */
        $tagsNotSeen = array_filter(
            $tagAvailability->getAvailableTagNames(),
            static function (string $tagName) use ($existingTags) {
                foreach ($existingTags as $tag) {
                    if ($tag->getName() === $tagName) {
                        return false;
                    }
                }

                return true;
            }
        );

        for ($page = 1; $page <= self::MAX_COMMIT_HISTORY_PAGES; ++$page) {
            if ($tagsNotSeen === []) {
                break;
            }

            // Theres still tags which we've seen in the past, but have not yet seen in
            // the commit tree. We'll keep looking for them in the tree until we find them
            [$tagsNotSeen, $tagsToCarryforward] = $this->lookForCarryforwardTagsInPaginatedTree(
                $event,
                $tagAvailability,
                $page,
                $tagsNotSeen
            );

            $carryforwardTags = [
                ...$carryforwardTags,
                ...$tagsToCarryforward
            ];
        }

        if ($tagsNotSeen !== []) {
            $this->carryforwardLogger->warning(
                sprintf(
                    'Could not find any commits to carry forward tags %s from for %s',
                    count($tagsNotSeen),
                    (string)$event
                ),
                [
                    'tagsNotSeen' => $tagsNotSeen,
                ]
            );
        }

        return $carryforwardTags;
    }

    /**
     * Look up the commit tree for an event (using pagination) and attempt to map the availability of
     * tags to commits, onto the ordered commit tree. In order to select the most recent
     * available tagged coverage for a commit.
     *
     * @param string[] $tagsNotSeen
     * @return array{0: string[], 1: Tag[]}
     */
    private function lookForCarryforwardTagsInPaginatedTree(
        EventInterface|ReportWaypoint $event,
        TagAvailabilityCollectionQueryResult $tagAvailability,
        int $page = 0,
        array $tagsNotSeen = []
    ): array {
        $carryforwardTags = [];

        if ($tagsNotSeen === []) {
            return [
                $tagsNotSeen,
                $carryforwardTags
            ];
        }

        $commitsFromTree = $this->commitHistoryService->getPrecedingCommits($event, $page);

        foreach ($tagsNotSeen as $index => $tagName) {
            $availability = $tagAvailability->getAvailabilityForTagName($tagName);

            // The commits in the tree will be in descending order, and the intersection will
            // tell us which of the tag's available commits is the newest in the tree
            $tagCommitsInTree = array_intersect(
                $commitsFromTree,
                $availability->getAvailableCommits(),
            );

            if ($tagCommitsInTree === []) {
                // This tag's commits isn't in the latest tree, so we can't carry it forward
                // yet.
                continue;
            }

            $newestCommit = reset($tagCommitsInTree);

            $this->carryforwardLogger->info(
                sprintf(
                    'Carrying forward tag %s from commit %s for %s',
                    $tagName,
                    $newestCommit,
                    (string)$event
                )
            );

            $carryforwardTags[] = new Tag(
                $tagName,
                $newestCommit
            );
            unset($tagsNotSeen[$index]);
        }

        return [
            $tagsNotSeen,
            $carryforwardTags
        ];
    }
}
