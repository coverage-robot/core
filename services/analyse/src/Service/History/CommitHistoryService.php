<?php

namespace App\Service\History;

use App\Exception\CommitHistoryException;
use App\Model\ReportWaypoint;
use Override;
use Packages\Contracts\Provider\ProviderAwareInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class CommitHistoryService implements CommitHistoryServiceInterface
{
    /**
     * The total number of commits which should be returned per page.
     */
    public const int COMMITS_TO_RETURN_PER_PAGE = 100;

    /**
     * @param (CommitHistoryServiceInterface&ProviderAwareInterface)[] $parsers
     */
    public function __construct(
        #[TaggedIterator(
            'app.commit_history',
            defaultIndexMethod: 'getProvider'
        )]
        private readonly iterable $parsers
    ) {
    }

    #[Override]
    public function getPrecedingCommits(ReportWaypoint $waypoint, int $page = 1): array
    {
        $service = (iterator_to_array($this->parsers)[$waypoint->getProvider()->value]) ?? null;

        if (!$service instanceof CommitHistoryServiceInterface) {
            throw new CommitHistoryException(
                sprintf(
                    'No commit history service for %s',
                    $waypoint->getProvider()->value
                )
            );
        }

        return $service->getPrecedingCommits($waypoint, $page);
    }
}
