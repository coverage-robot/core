<?php

namespace App\Service\History;

use App\Service\ProviderAwareInterface;
use Packages\Models\Model\Event\EventInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class CommitHistoryService implements CommitHistoryServiceInterface
{
    /**
     * @param (CommitHistoryServiceInterface&ProviderAwareInterface)[] $parsers
     */
    public function __construct(
        #[TaggedIterator(
            'app.commit_history',
            defaultIndexMethod: 'getProvider',
            exclude: ['CommitHistoryService']
        )]
        private readonly iterable $parsers
    ) {
    }

    /**
     * Get the commits which preceded the given upload - these are the parent commits
     * of the one recorded during the upload.
     *
     * @throws RuntimeException
     */
    public function getPrecedingCommits(EventInterface $event): array
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

        return $service->getPrecedingCommits($event);
    }
}
