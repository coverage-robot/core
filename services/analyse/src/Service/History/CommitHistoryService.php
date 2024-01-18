<?php

namespace App\Service\History;

use App\Model\ReportWaypoint;
use App\Service\ProviderAwareInterface;
use Override;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class CommitHistoryService implements CommitHistoryServiceInterface
{
    /**
     * The total number of commits which should be returned per page.
     */
    final public const COMMITS_TO_RETURN_PER_PAGE = 100;

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

    /**
     * @return array{commit: string, merged: bool, ref: string|null}[]
     *
     * @throws RuntimeException
     */
    #[Override]
    public function getPrecedingCommits(ReportWaypoint $waypoint, int $page = 1): array
    {
        $service = (iterator_to_array($this->parsers)[$waypoint->getProvider()->value]) ?? null;

        if (!$service instanceof CommitHistoryServiceInterface) {
            throw new RuntimeException(
                sprintf(
                    'No commit history service for %s',
                    $waypoint->getProvider()->value
                )
            );
        }

        return $service->getPrecedingCommits($waypoint, $page);
    }
}
