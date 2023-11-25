<?php

namespace App\Service\History;

use App\Service\ProviderAwareInterface;
use Packages\Contracts\Event\EventInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class CommitHistoryService
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
     * Get the commits which preceded a given commit in the tree.
     *
     * @return string[]
     * @throws RuntimeException
     */
    public function getPrecedingCommits(EventInterface $event, int $page = 1): array
    {
        $service = (iterator_to_array($this->parsers)[$event->getProvider()->value]) ?? null;

        if (!$service instanceof CommitHistoryServiceInterface) {
            throw new RuntimeException(
                sprintf(
                    'No commit history service for %s',
                    $event->getProvider()->value
                )
            );
        }

        return $service->getPrecedingCommits($event, $page);
    }
}
