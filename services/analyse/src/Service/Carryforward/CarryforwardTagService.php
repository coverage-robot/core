<?php

namespace App\Service\Carryforward;

use App\Exception\QueryException;
use App\Model\CarryforwardTag;
use App\Model\QueryParameterBag;
use App\Model\ReportWaypoint;
use App\Query\Result\TagAvailabilityCollectionQueryResult;
use App\Query\TagAvailabilityQuery;
use App\Service\CachingQueryService;
use App\Service\History\CommitHistoryService;
use App\Service\QueryServiceInterface;
use Override;
use Packages\Configuration\Service\TagBehaviourService;
use Packages\Contracts\Tag\Tag;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CarryforwardTagService implements CarryforwardTagServiceInterface
{
    /**
     * The total number of pages to look back through in order to match the available tags we've seen
     * uploaded in the past, and the most recent commits in the tree.
     *
     * @see CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE
     */
    private const int MAX_COMMIT_HISTORY_PAGES = 5;

    public function __construct(
        #[Autowire(service: CachingQueryService::class)]
        private readonly QueryServiceInterface $queryService,
        private readonly TagBehaviourService $tagBehaviourService,
        private readonly LoggerInterface $carryforwardLogger
    ) {
    }

    /**
     * @param Tag[] $existingTags
     * @return CarryforwardTag[]
     * @throws QueryException
     */
    #[Override]
    public function getTagsToCarryforward(ReportWaypoint $waypoint, array $existingTags): array
    {
        $carryforwardTags = [];

        /** @var TagAvailabilityCollectionQueryResult $tagAvailability */
        $tagAvailability = $this->queryService->runQuery(
            TagAvailabilityQuery::class,
            QueryParameterBag::fromWaypoint($waypoint)
        );

        /**
         * @var string[] $tagsNotSeen
         */
        $tagsNotSeen = array_filter(
            $tagAvailability->getAvailableTagNames(),
            fn(string $tagName): bool => $this->shouldTagBeCarriedForward($waypoint, $existingTags, $tagName)
        );

        for ($page = 1; $page <= self::MAX_COMMIT_HISTORY_PAGES; ++$page) {
            if ($tagsNotSeen === []) {
                break;
            }

            // Theres still tags which we've seen in the past, but have not yet seen in
            // the commit tree. We'll keep looking for them in the tree until we find them
            [
                $tagsNotSeen,
                $tagsToCarryforward,
                $hasReachedEndOfHistory
            ] = $this->lookForCarryforwardTagsInPaginatedTree(
                $waypoint,
                $tagAvailability,
                $page,
                $tagsNotSeen
            );

            $carryforwardTags = [
                ...$carryforwardTags,
                ...$tagsToCarryforward
            ];

            if ($hasReachedEndOfHistory) {
                // We've reached the end of the commit history, so we can safely break out now
                // and use whatever tags we've found as carryforward
                break;
            }
        }

        if ($tagsNotSeen !== []) {
            $this->carryforwardLogger->warning(
                sprintf(
                    'Could not find any commits to carry forward %s tags from for %s',
                    count($tagsNotSeen),
                    (string)$waypoint
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
     * @return array{0: string[], 1: CarryforwardTag[], 2: bool}
     */
    private function lookForCarryforwardTagsInPaginatedTree(
        ReportWaypoint $waypoint,
        TagAvailabilityCollectionQueryResult $tagAvailability,
        int $page = 0,
        array $tagsNotSeen = []
    ): array {
        $carryforwardTags = [];

        if ($tagsNotSeen === []) {
            return [
                $tagsNotSeen,
                $carryforwardTags,
                false
            ];
        }

        $commitsFromTree = $waypoint->getHistory($page);

        foreach ($tagsNotSeen as $index => $tagName) {
            $availability = $tagAvailability->getAvailabilityForTagName($tagName);

            // The commits in the tree will be in descending order, and the intersection will
            // tell us which of the tag's available commits is the newest in the tree

            $carryforwardTag = null;
            foreach ($commitsFromTree as $commitFromTree) {
                foreach ($availability->getCarryforwardTags() as $availableTag) {
                    if ($commitFromTree['commit'] !== $availableTag->getCommit()) {
                        continue;
                    }

                    $carryforwardTag = $availableTag;
                    break 2;
                }
            }

            if ($carryforwardTag === null) {
                // This tag's commits isn't in the latest tree, so we can't carry it forward
                // yet.
                continue;
            }

            $this->carryforwardLogger->info(
                sprintf(
                    'Carrying forward tag %s from commit %s for %s',
                    $carryforwardTag->getName(),
                    $carryforwardTag->getCommit(),
                    (string)$waypoint
                )
            );

            $carryforwardTags[] = $carryforwardTag;

            unset($tagsNotSeen[$index]);
        }

        return [
            $tagsNotSeen,
            $carryforwardTags,
            count($commitsFromTree) < CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE
        ];
    }

    /**
     * Decide if a tag should be considered as being able to be carried forward.
     *
     * This looks at the existing tags as well as the tag behaviour configuration to decide
     * if a tag should be carried forward.
     *
     * @param Tag[] $existingTags
     */
    private function shouldTagBeCarriedForward(
        ReportWaypoint $waypoint,
        array $existingTags,
        string $tagName
    ): bool {
        if (
            !$this->tagBehaviourService->shouldCarryforwardTag(
                $waypoint->getProvider(),
                $waypoint->getOwner(),
                $waypoint->getRepository(),
                $tagName
            )
        ) {
            // This tag shouldn't be carried forward as its excluded in configuration, so
            // we can skip it
            return false;
        }

        foreach ($existingTags as $existingTag) {
            if ($existingTag->getName() !== $tagName) {
                continue;
            }

            // The tags already been seen, so should be skipped
            return false;
        }

        return true;
    }
}
