<?php

namespace App\Service\History;

use App\Model\ReportWaypoint;
use App\Service\ProviderAwareInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use WeakMap;

class CachingCommitHistoryService extends CommitHistoryService
{
    /**
     * @var WeakMap<ReportWaypoint, array<int, array{commit: string, isOnBaseRef: bool}[]>>
     */
    private WeakMap $cache;

    /**
     * @param (CommitHistoryServiceInterface&ProviderAwareInterface)[] $parsers
     */
    public function __construct(
        #[TaggedIterator(
            'app.commit_history',
            defaultIndexMethod: 'getProvider'
        )]
        iterable $parsers
    ) {
        parent::__construct($parsers);

        /**
         * @var WeakMap<ReportWaypoint, array<int, array{commit: string, isOnBaseRef: bool}[]>> $cache
         */
        $cache = new WeakMap();

        $this->cache = $cache;
    }

    public function getPrecedingCommits(ReportWaypoint $waypoint, int $page = 1): array
    {
        if (!isset($this->cache[$waypoint][$page])) {
            $this->cache[$waypoint] = array_replace(
                $this->cache[$waypoint] ?? [],
                [
                    $page => parent::getPrecedingCommits($waypoint, $page)
                ]
            );
        }

        return $this->cache[$waypoint][$page];
    }
}
